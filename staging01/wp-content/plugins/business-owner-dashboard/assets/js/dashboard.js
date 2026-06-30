
document.addEventListener("DOMContentLoaded", function () {
    /* Sidebar Toggle */
    const tfxMainWrapper = document.getElementById("tfxMainWrapper");
    const tfxSidebarToggleBtn = document.getElementById("tfxSidebarToggleBtn");
    const tfxScreenOverlay = document.getElementById("tfxScreenOverlay");
    const tfxSidebarCloseBtn = document.getElementById("tfxSidebarCloseBtn");

    function tfxCheckMobileScreen() {
        return window.innerWidth <= 991;
    }

    if (tfxSidebarToggleBtn && tfxMainWrapper) {
        tfxSidebarToggleBtn.addEventListener("click", function () {
            if (tfxCheckMobileScreen()) {
                tfxMainWrapper.classList.toggle("tfx-mobile-sidebar-open");
            } else {
                tfxMainWrapper.classList.toggle("tfx-sidebar-closed");
            }
        });
    }

    function tfxCloseMobileSidebar() {
        if (tfxMainWrapper) {
            tfxMainWrapper.classList.remove("tfx-mobile-sidebar-open");
        }
    }

    if (tfxScreenOverlay && tfxMainWrapper) {
        tfxScreenOverlay.addEventListener("click", tfxCloseMobileSidebar);
    }

    if (tfxSidebarCloseBtn) {
        tfxSidebarCloseBtn.addEventListener("click", tfxCloseMobileSidebar);
    }

    document.querySelectorAll(".tfx-navigation-link").forEach(function (linkItem) {
        linkItem.addEventListener("click", function () {
            if (tfxCheckMobileScreen()) {
                tfxCloseMobileSidebar();
            }
        });
    });

    window.addEventListener("resize", function () {
        if (tfxMainWrapper && !tfxCheckMobileScreen()) {
            tfxMainWrapper.classList.remove("tfx-mobile-sidebar-open");
        }
    });

    /* Count Animation */
    function tfxAnimateCounter(element, target, duration = 1300) {
        let startTime = null;

        function updateCounter(currentTime) {
            if (!startTime) startTime = currentTime;

            const progress = Math.min((currentTime - startTime) / duration, 1);
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            const currentValue = Math.floor(target * easedProgress);

            element.textContent = currentValue.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = target.toLocaleString();
            }
        }

        requestAnimationFrame(updateCounter);
    }

    document.querySelectorAll("[data-tfx-counter]").forEach(function (counterItem) {
        const targetValue = Number(counterItem.getAttribute("data-tfx-counter"));
        tfxAnimateCounter(counterItem, targetValue);
    });

    /* Professional Date Range Calendar */
    const tfxDatePickerWrapper = document.querySelector(".tfx-date-picker-wrapper");
    const tfxDateRangeBtn = document.getElementById("tfxDateRangeBtn");
    const tfxCalendarDropdown = document.getElementById("tfxCalendarDropdown");
    const tfxCalendarCloseBtn = document.getElementById("tfxCalendarCloseBtn");
    const tfxCalendarCancelBtn = document.getElementById("tfxCalendarCancelBtn");
    const tfxApplyCalendarBtn = document.getElementById("tfxApplyCalendarBtn");
    const tfxDateRangeText = document.getElementById("tfxDateRangeText");
    const tfxSelectedDatePreview = document.getElementById("tfxSelectedDatePreview");
    const tfxStartDateInput = document.getElementById("tfxStartDateInput");
    const tfxEndDateInput = document.getElementById("tfxEndDateInput");
    const tfxPresetButtons = document.querySelectorAll(".tfx-calendar-preset-btn");

    let tfxSelectedStartDate = new Date("2025-05-22T00:00:00");
    let tfxSelectedEndDate = new Date("2025-06-21T00:00:00");
    let tfxProfessionalCalendar = null;

    function tfxDateToYMD(dateObject) {
        const year = dateObject.getFullYear();
        const month = String(dateObject.getMonth() + 1).padStart(2, "0");
        const day = String(dateObject.getDate()).padStart(2, "0");

        return year + "-" + month + "-" + day;
    }

    function tfxFormatCalendarDate(dateObject) {
        return dateObject.toLocaleDateString("en-AU", {
            day: "2-digit",
            month: "short",
            year: "numeric"
        });
    }

    function tfxUpdateCalendarPreview(startDate, endDate) {
        if (!tfxSelectedDatePreview) {
            return;
        }

        if (!startDate || !endDate) {
            tfxSelectedDatePreview.textContent = "Select start and end date";
            return;
        }

        tfxSelectedDatePreview.textContent =
            tfxFormatCalendarDate(startDate) + " - " + tfxFormatCalendarDate(endDate);
    }

    function tfxOpenCalendar() {
        if (tfxDatePickerWrapper) {
            tfxDatePickerWrapper.classList.add("tfx-calendar-open");
        }
    }

    function tfxCloseCalendar() {
        if (tfxDatePickerWrapper) {
            tfxDatePickerWrapper.classList.remove("tfx-calendar-open");
        }
    }

    if (typeof flatpickr !== "undefined" && document.getElementById("tfxDateRangeCalendarInput")) {
        tfxProfessionalCalendar = flatpickr("#tfxDateRangeCalendarInput", {
            mode: "range",
            inline: true,
            dateFormat: "Y-m-d",
            defaultDate: ["2025-05-22", "2025-06-21"],
            disableMobile: true,
            onChange: function (selectedDates) {
                tfxPresetButtons.forEach(function (btn) {
                    btn.classList.remove("tfx-preset-active");
                });

                if (selectedDates.length === 1) {
                    tfxSelectedStartDate = selectedDates[0];
                    tfxSelectedEndDate = null;
                    tfxUpdateCalendarPreview(tfxSelectedStartDate, null);
                }

                if (selectedDates.length === 2) {
                    tfxSelectedStartDate = selectedDates[0];
                    tfxSelectedEndDate = selectedDates[1];
                    tfxUpdateCalendarPreview(tfxSelectedStartDate, tfxSelectedEndDate);
                }
            }
        });
    }

    if (tfxDateRangeBtn && tfxDatePickerWrapper) {
        tfxDateRangeBtn.addEventListener("click", function (event) {
            event.stopPropagation();
            tfxDatePickerWrapper.classList.toggle("tfx-calendar-open");
        });
    }

    if (tfxCalendarDropdown) {
        tfxCalendarDropdown.addEventListener("click", function (event) {
            event.stopPropagation();
        });
    }

    if (tfxCalendarCloseBtn) {
        tfxCalendarCloseBtn.addEventListener("click", tfxCloseCalendar);
    }

    if (tfxCalendarCancelBtn) {
        tfxCalendarCancelBtn.addEventListener("click", tfxCloseCalendar);
    }

    document.addEventListener("click", function () {
        tfxCloseCalendar();
    });

    tfxPresetButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const rangeType = button.getAttribute("data-range");

            tfxPresetButtons.forEach(function (btn) {
                btn.classList.remove("tfx-preset-active");
            });

            button.classList.add("tfx-preset-active");

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let startDate = new Date(today);
            let endDate = new Date(today);

            if (rangeType === "7") {
                startDate.setDate(today.getDate() - 6);
            } else if (rangeType === "30") {
                startDate.setDate(today.getDate() - 29);
            } else if (rangeType === "90") {
                startDate.setDate(today.getDate() - 89);
            } else if (rangeType === "month") {
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            }

            tfxSelectedStartDate = startDate;
            tfxSelectedEndDate = endDate;

            if (tfxProfessionalCalendar) {
                tfxProfessionalCalendar.setDate(
                    [tfxDateToYMD(startDate), tfxDateToYMD(endDate)],
                    true
                );
            }

            tfxUpdateCalendarPreview(startDate, endDate);
        });
    });

    if (tfxApplyCalendarBtn) {
        tfxApplyCalendarBtn.addEventListener("click", function () {
            if (!tfxSelectedStartDate || !tfxSelectedEndDate) {
                return;
            }

            const startValue = tfxDateToYMD(tfxSelectedStartDate);
            const endValue = tfxDateToYMD(tfxSelectedEndDate);

            if (tfxStartDateInput) {
                tfxStartDateInput.value = startValue;
            }

            if (tfxEndDateInput) {
                tfxEndDateInput.value = endValue;
            }

            if (tfxDateRangeText) {
                tfxDateRangeText.textContent =
                    tfxFormatCalendarDate(tfxSelectedStartDate) + " - " + tfxFormatCalendarDate(tfxSelectedEndDate);
            }

            tfxCloseCalendar();

            if (typeof tfxDrawProfileGraph === "function") {
                tfxDrawProfileGraph(30);
            }
        });
    }

    tfxUpdateCalendarPreview(tfxSelectedStartDate, tfxSelectedEndDate);

    /* Working SVG Graph */
    const tfxProfileChartData = {
        30: {
            labels: ["22 May", "24 May", "26 May", "28 May", "30 May", "1 Jun", "3 Jun", "5 Jun", "7 Jun", "9 Jun", "11 Jun", "13 Jun", "15 Jun", "17 Jun", "19 Jun", "21 Jun"],
            values: [260, 330, 290, 410, 360, 590, 470, 520, 350, 310, 430, 560, 490, 290, 380, 590]
        },
        14: {
            labels: ["8 Jun", "9 Jun", "10 Jun", "11 Jun", "12 Jun", "13 Jun", "14 Jun", "15 Jun", "16 Jun", "17 Jun", "18 Jun", "19 Jun", "20 Jun", "21 Jun"],
            values: [310, 330, 420, 510, 560, 490, 360, 290, 330, 380, 340, 470, 590, 520]
        },
        7: {
            labels: ["15 Jun", "16 Jun", "17 Jun", "18 Jun", "19 Jun", "20 Jun", "21 Jun"],
            values: [290, 330, 380, 340, 470, 590, 520]
        }
    };

    const tfxGraphGridLines = document.getElementById("tfxGraphGridLines");
    const tfxGraphAreaPath = document.getElementById("tfxGraphAreaPath");
    const tfxGraphLinePath = document.getElementById("tfxGraphLinePath");
    const tfxGraphDots = document.getElementById("tfxGraphDots");
    const tfxGraphXAxisLabels = document.getElementById("tfxGraphXAxisLabels");
    const tfxGraphTooltip = document.getElementById("tfxGraphTooltip");

    function tfxBuildSmoothGraphPath(points) {
        if (!points.length) {
            return "";
        }

        let graphPath = "M " + points[0].x + " " + points[0].y;

        for (let i = 1; i < points.length; i++) {
            const previousPoint = points[i - 1];
            const currentPoint = points[i];
            const controlX = (previousPoint.x + currentPoint.x) / 2;

            graphPath +=
                " C " +
                controlX +
                " " +
                previousPoint.y +
                ", " +
                controlX +
                " " +
                currentPoint.y +
                ", " +
                currentPoint.x +
                " " +
                currentPoint.y;
        }

        return graphPath;
    }

    window.tfxDrawProfileGraph = function (range = 30) {
        if (
            !tfxGraphGridLines ||
            !tfxGraphAreaPath ||
            !tfxGraphLinePath ||
            !tfxGraphDots ||
            !tfxGraphXAxisLabels ||
            !tfxGraphTooltip
        ) {
            return;
        }

        const chartObject = tfxProfileChartData[range];

        if (!chartObject) {
            return;
        }

        const values = chartObject.values;
        const labels = chartObject.labels;

        const svgWidth = 620;
        const svgHeight = 260;
        const paddingTop = 16;
        const paddingRight = 12;
        const paddingBottom = 28;
        const paddingLeft = 10;

        const graphWidth = svgWidth - paddingLeft - paddingRight;
        const graphHeight = svgHeight - paddingTop - paddingBottom;

        const minValue = 200;
        const maxValue = 800;

        const points = values.map(function (value, index) {
            const x = paddingLeft + (index * graphWidth) / (values.length - 1);
            const y = paddingTop + ((maxValue - value) / (maxValue - minValue)) * graphHeight;

            return {
                x: x,
                y: y,
                value: value,
                label: labels[index]
            };
        });

        tfxGraphGridLines.innerHTML = "";
        tfxGraphDots.innerHTML = "";
        tfxGraphXAxisLabels.innerHTML = "";

        [200, 400, 600, 800].forEach(function (lineValue) {
            const y = paddingTop + ((maxValue - lineValue) / (maxValue - minValue)) * graphHeight;

            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", paddingLeft);
            line.setAttribute("x2", svgWidth - paddingRight);
            line.setAttribute("y1", y);
            line.setAttribute("y2", y);
            line.setAttribute("stroke", "#eef2f7");
            line.setAttribute("stroke-width", "1");

            tfxGraphGridLines.appendChild(line);
        });

        const linePath = tfxBuildSmoothGraphPath(points);

        const areaPath =
            linePath +
            " L " +
            points[points.length - 1].x +
            " " +
            (svgHeight - paddingBottom) +
            " L " +
            points[0].x +
            " " +
            (svgHeight - paddingBottom) +
            " Z";

        tfxGraphLinePath.setAttribute("d", linePath);
        tfxGraphAreaPath.setAttribute("d", areaPath);

        const pathLength = tfxGraphLinePath.getTotalLength();

        tfxGraphLinePath.style.strokeDasharray = pathLength;
        tfxGraphLinePath.style.strokeDashoffset = pathLength;
        tfxGraphLinePath.style.transition = "none";

        requestAnimationFrame(function () {
            tfxGraphLinePath.style.transition = "stroke-dashoffset 1.2s ease";
            tfxGraphLinePath.style.strokeDashoffset = "0";
        });

        points.forEach(function (point) {
            const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");

            circle.setAttribute("cx", point.x);
            circle.setAttribute("cy", point.y);
            circle.setAttribute("r", "4");
            circle.setAttribute("fill", "#ffffff");
            circle.setAttribute("stroke", "#1062ff");
            circle.setAttribute("stroke-width", "2");
            circle.classList.add("tfx-graph-dot");

            circle.addEventListener("mouseenter", function () {
                const xPercent = point.x / svgWidth;
                const yPercent = point.y / svgHeight;

                tfxGraphTooltip.innerHTML = point.label + "<br>" + point.value + " views";
                tfxGraphTooltip.style.display = "block";
                tfxGraphTooltip.style.left = "calc(" + xPercent * 100 + "% + 18px)";
                tfxGraphTooltip.style.top = "calc(" + yPercent * 100 + "% - 14px)";
                tfxGraphTooltip.style.transform = "translate(-50%, -100%)";
            });

            circle.addEventListener("mouseleave", function () {
                tfxGraphTooltip.style.display = "none";
            });

            tfxGraphDots.appendChild(circle);
        });

        const labelStep = Number(range) === 7 ? 1 : Number(range) === 14 ? 3 : 5;

        labels.forEach(function (label, index) {
            if (index % labelStep === 0 || index === labels.length - 1) {
                const labelSpan = document.createElement("span");
                labelSpan.textContent = label;
                tfxGraphXAxisLabels.appendChild(labelSpan);
            }
        });
    };

    const tfxChartRangeSelect = document.getElementById("tfxChartRangeSelect");

    if (tfxChartRangeSelect) {
        tfxChartRangeSelect.addEventListener("change", function () {
            window.tfxDrawProfileGraph(this.value);
        });
    }

    window.tfxDrawProfileGraph(30);

    /* Profile Strength Ring */
    const tfxProfileRing = document.getElementById("tfxProfileRing");
    const tfxProfileRingValue = document.getElementById("tfxProfileRingValue");

    function tfxAnimateProfileRing(targetPercentage) {
        let currentPercentage = 0;

        const ringTimer = setInterval(function () {
            currentPercentage++;

            const degree = currentPercentage * 3.6;

            tfxProfileRing.style.background =
                "conic-gradient(#1062ff " + degree + "deg, #e8eef8 " + degree + "deg)";

            tfxProfileRingValue.textContent = currentPercentage + "%";

            if (currentPercentage >= targetPercentage) {
                clearInterval(ringTimer);
            }
        }, 14);
    }

    if (tfxProfileRing && tfxProfileRingValue) {
        const tfxRingPercentage = Number(tfxProfileRing.getAttribute("data-ring-percentage"));
        tfxAnimateProfileRing(tfxRingPercentage);
    }

    /* Task Progress */
    const tfxTaskCheckboxes = document.querySelectorAll(".tfx-task-checkbox");
    const tfxTaskProgressFill = document.getElementById("tfxTaskProgressFill");
    const tfxTaskCounterText = document.getElementById("tfxTaskCounterText");

    function tfxUpdateTaskProgress() {
        const totalTasks = tfxTaskCheckboxes.length;

        const completedTasks = Array.from(tfxTaskCheckboxes).filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        const progressPercentage = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

        if (tfxTaskProgressFill) {
            tfxTaskProgressFill.style.width = progressPercentage + "%";
        }

        if (tfxTaskCounterText) {
            tfxTaskCounterText.textContent = completedTasks + "/" + totalTasks;
        }

        tfxTaskCheckboxes.forEach(function (checkbox) {
            const taskTitle = checkbox.closest(".tfx-task-item").querySelector(".tfx-task-title");

            if (checkbox.checked) {
                taskTitle.classList.add("tfx-task-completed");
            } else {
                taskTitle.classList.remove("tfx-task-completed");
            }
        });
    }

    tfxTaskCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener("change", tfxUpdateTaskProgress);
    });

    setTimeout(tfxUpdateTaskProgress, 250);

    /* User Profile Dropdown */
    const tfxUserDropdownBtn = document.getElementById("tfxUserDropdownBtn");
    const tfxUserDropdownMenu = document.getElementById("tfxUserDropdownMenu");
    const tfxUserDropdownWrapper = document.querySelector(".tfx-user-dropdown-wrapper");

    if (tfxUserDropdownBtn && tfxUserDropdownMenu && tfxUserDropdownWrapper) {
        tfxUserDropdownBtn.addEventListener("click", function (event) {
            event.stopPropagation();
            tfxUserDropdownWrapper.classList.toggle("tfx-profile-open");
        });

        tfxUserDropdownMenu.addEventListener("click", function (event) {
            event.stopPropagation();
        });

        document.addEventListener("click", function () {
            tfxUserDropdownWrapper.classList.remove("tfx-profile-open");
        });
    }

    /* Notification Count */
    const tfxNotificationCount = document.querySelector(".tfx-notification-count");

    if (tfxNotificationCount) {
        tfxNotificationCount.textContent = "7";
    }
});















