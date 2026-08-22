let selectedDate = new Date();

const dateElement = document.getElementById("selectedDate");
const currentDateElement = document.getElementById("currentDate");
const datePicker = document.getElementById("datePicker");

const previousDay = document.getElementById("previousDay");
const nextDay = document.getElementById("nextDay");
const todayButton = document.getElementById("todayBtn");
const searchInput = document.getElementById("searchInput");
const tableBody = document.querySelector("#attendanceTable tbody");
const absentCount = document.getElementById("absentCount");
const lateCount = document.getElementById("lateCount");

// ===============================
// ATTENDANCE DATA
// ===============================

const attendanceData = {

    "2026-08-22": [
        ["Rahul Sharma", "EMP001", "09:00 AM", "06:00 PM", "8h 00m", "1h 00m", "Present"],
        ["Ananya Rao", "EMP002", "09:15 AM", "05:30 PM", "7h 45m", "0h 45m", "Late"],
        ["Priya Nair", "EMP003", "--", "--", "--", "--", "Absent"],
        ["Arjun Kumar", "EMP004", "08:55 AM", "05:45 PM", "8h 10m", "0h 10m", "Present"]
    ],

    "2026-08-21": [
        ["Rahul Sharma", "EMP001", "09:05 AM", "06:00 PM", "7h 55m", "0h 55m", "Late"],
        ["Ananya Rao", "EMP002", "09:00 AM", "05:45 PM", "7h 45m", "0h 45m", "Present"],
        ["Priya Nair", "EMP003", "09:10 AM", "06:00 PM", "7h 50m", "0h 50m", "Late"],
        ["Arjun Kumar", "EMP004", "--", "--", "--", "--", "Absent"]
    ],

    "2026-08-20": [
        ["Rahul Sharma", "EMP001", "09:00 AM", "06:00 PM", "8h 00m", "1h 00m", "Present"],
        ["Ananya Rao", "EMP002", "09:20 AM", "05:30 PM", "7h 40m", "0h 40m", "Late"],
        ["Priya Nair", "EMP003", "09:00 AM", "05:45 PM", "7h 45m", "0h 45m", "Present"],
        ["Arjun Kumar", "EMP004", "09:05 AM", "05:30 PM", "7h 25m", "0h 25m", "Late"]
    ]

};


// ===============================
// GET DATE KEY
// ===============================

function getDateKey(date) {

    const year = date.getFullYear();

    const month = String(
        date.getMonth() + 1
    ).padStart(2, "0");

    const day = String(
        date.getDate()
    ).padStart(2, "0");

    return `${year}-${month}-${day}`;
}


// ===============================
// FORMAT DATE
// ===============================

function formatDate(date) {

    return date.toLocaleDateString("en-IN", {
        day: "2-digit",
        month: "long",
        year: "numeric"
    });
}


// ===============================
// DISPLAY ATTENDANCE
// ===============================
function updateSummary(records) {

    let present = 0;
    let absent = 0;
    let late = 0;

    records.forEach(function(record) {

        const status = record[6].toLowerCase();

        if (status === "present") {
            present++;
        }

        if (status === "absent") {
            absent++;
        }

        if (status === "late") {
            late++;
        }

    });

    if (presentCount) {
        presentCount.textContent = present;
    }

    if (absentCount) {
        absentCount.textContent = absent;
    }

    if (lateCount) {
        lateCount.textContent = late;
    }
}

function renderAttendance() {

    if (!tableBody) return;

    const dateKey = getDateKey(selectedDate);

    const records = attendanceData[dateKey] || [];

    updateSummary(records);
    
    tableBody.innerHTML = "";

    records.forEach(function(record) {

        const row = document.createElement("tr");

        row.innerHTML = `
            <td>${record[0]}</td>
            <td>${record[1]}</td>
            <td>${record[2]}</td>
            <td>${record[3]}</td>
            <td>${record[4]}</td>
            <td>${record[5]}</td>
            <td>
                <span class="status ${record[6].toLowerCase()}">
                    ${record[6]}
                </span>
            </td>
        `;

        tableBody.appendChild(row);

    });

    applySearch();
}


// ===============================
// UPDATE DATE
// ===============================

function updateDate() {

    if (dateElement) {
        dateElement.textContent =
            formatDate(selectedDate);
    }

    if (currentDateElement) {
        currentDateElement.textContent =
            formatDate(selectedDate);
    }

    if (datePicker) {
        datePicker.value =
            getDateKey(selectedDate);
    }

    renderAttendance();
}


// ===============================
// PREVIOUS DAY
// ===============================

if (previousDay) {

    previousDay.addEventListener("click", function() {

        selectedDate.setDate(
            selectedDate.getDate() - 1
        );

        updateDate();

    });

}


// ===============================
// NEXT DAY
// ===============================

if (nextDay) {

    nextDay.addEventListener("click", function() {

        selectedDate.setDate(
            selectedDate.getDate() + 1
        );

        updateDate();

    });

}


// ===============================
// TODAY
// ===============================

if (todayButton) {

    todayButton.addEventListener("click", function() {

        selectedDate = new Date();

        updateDate();

    });

}


// ===============================
// DATE PICKER
// ===============================

if (datePicker) {

    datePicker.addEventListener("change", function() {

        const selected =
            new Date(this.value + "T00:00:00");

        if (!isNaN(selected.getTime())) {

            selectedDate = selected;

            updateDate();

        }

    });

}


// ===============================
// SEARCH EMPLOYEE
// ===============================

function applySearch() {

    if (!searchInput || !tableBody) return;

    const searchText =
        searchInput.value.toLowerCase().trim();

    const rows =
        tableBody.querySelectorAll("tr");

    rows.forEach(function(row) {

        const employeeName =
            row.cells[0]?.textContent.toLowerCase() || "";

        const employeeId =
            row.cells[1]?.textContent.toLowerCase() || "";

        if (
            employeeName.includes(searchText) ||
            employeeId.includes(searchText)
        ) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    });

}


if (searchInput) {

    searchInput.addEventListener("input", function() {

        applySearch();

    });

}


// ===============================
// INITIAL LOAD
// ===============================

updateDate();