// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript AMD controller for asynchronous dashboard rendering, customizable charts,
 * 10 pre-built widget types, and coordinated per-dashboard global & custom filters.
 *
 * @module     local_lms_dashboard_builder/dashboard_view
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function (ajax) {
    'use strict';

    /**
     * Built-in color palettes for customizable charts and status distributions.
     */
    var PALETTES = {
        modern: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'],
        ocean: ['#0284c7', '#0ea5e9', '#38bdf8', '#06b6d4', '#14b8a6', '#2dd4bf', '#6366f1'],
        emerald: ['#059669', '#10b981', '#34d399', '#0d9488', '#84cc16', '#22c55e', '#15803d'],
        sunset: ['#e11d48', '#f43f5e', '#f97316', '#f59e0b', '#eab308', '#ec4899', '#8b5cf6'],
        monochrome: ['#1e293b', '#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1', '#0f172a']
    };

    /**
     * Dashboard View controller class.
     *
     * @param {number} dashboardId Dashboard ID.
     * @param {number} contextId Context ID.
     * @param {Object} initialFilters Initial filter state.
     * @param {string} [instanceId] Unique placement instance identifier.
     */
    var DashboardView = function (dashboardId, contextId, initialFilters, instanceId) {
        this.dashboardId = dashboardId;
        this.contextId = contextId || 1;
        this.instanceId = instanceId ? String(instanceId) : String(dashboardId);
        this.currentFilters = initialFilters || { daterange: '30d' };
        this.chartInstances = {};
        this.root = document.getElementById('lms-dashboard-' + this.instanceId) ||
            document.getElementById('lms-dashboard-' + dashboardId);
        if (!this.root) {
            return;
        }
        this.collectFilterValues();
        this.initEventListeners();
        this.loadAllWidgets();
    };

    /**
     * Read current values from all configured filter inputs in the dashboard filter bar.
     */
    DashboardView.prototype.collectFilterValues = function () {
        var self = this;
        var filterBar = this.root.querySelector('.dashboard-filter-bar');
        if (!filterBar) {
            return;
        }
        var inputs = filterBar.querySelectorAll('.filter-input');
        inputs.forEach(function (input) {
            var name = input.getAttribute('name');
            if (name) {
                self.currentFilters[name] = input.value;
            }
        });
    };

    /**
     * Bind filter bar events scoped to this dashboard instance.
     */
    DashboardView.prototype.initEventListeners = function () {
        var self = this;
        var filterBar = this.root.querySelector('.dashboard-filter-bar');
        if (!filterBar) {
            return;
        }

        // Dropdown cascading: Category -> Course, Course -> Group.
        var categorySelect = filterBar.querySelector('select[data-filter="categoryid"]');
        var courseSelect = filterBar.querySelector('select[data-filter="courseid"]');
        var groupSelect = filterBar.querySelector('select[data-filter="groupid"]');

        var handleCourseChange = function () {
            if (!courseSelect || !groupSelect) {
                return;
            }
            var selectedCourse = courseSelect.value;
            var groupOptions = groupSelect.querySelectorAll('option');
            var firstVisibleGroup = null;
            groupOptions.forEach(function (opt) {
                var optCourse = opt.getAttribute('data-courseid');
                var match = (!selectedCourse ||
                    selectedCourse === '0' ||
                    !optCourse || optCourse === '0' || optCourse === selectedCourse);
                opt.style.display = match ? '' : 'none';
                opt.disabled = !match;
                if (match && !firstVisibleGroup) {
                    firstVisibleGroup = opt;
                }
            });
            if (groupSelect.selectedOptions.length === 0 || groupSelect.selectedOptions[0].disabled) {
                if (firstVisibleGroup) {
                    firstVisibleGroup.selected = true;
                }
            }
        };

        var handleCategoryChange = function () {
            if (!categorySelect || !courseSelect) {
                return;
            }
            var selectedCat = categorySelect.value;
            var courseOptions = courseSelect.querySelectorAll('option');
            var firstVisibleCourse = null;
            courseOptions.forEach(function (opt) {
                var optCat = opt.getAttribute('data-categoryid');
                var match = (!selectedCat || selectedCat === '0' || !optCat || optCat === '0' || optCat === selectedCat);
                opt.style.display = match ? '' : 'none';
                opt.disabled = !match;
                if (match && !firstVisibleCourse) {
                    firstVisibleCourse = opt;
                }
            });
            if (courseSelect.selectedOptions.length === 0 || courseSelect.selectedOptions[0].disabled) {
                if (firstVisibleCourse) {
                    firstVisibleCourse.selected = true;
                }
            }
            if (groupSelect) {
                handleCourseChange();
            }
        };

        if (categorySelect && courseSelect) {
            categorySelect.addEventListener('change', handleCategoryChange);
        }

        if (courseSelect && groupSelect) {
            courseSelect.addEventListener('change', handleCourseChange);
        }

        // Run initial cascading check.
        if (categorySelect && courseSelect) {
            handleCategoryChange();
        } else if (courseSelect && groupSelect) {
            handleCourseChange();
        }

        var applyBtn = filterBar.querySelector('.btn-apply-filters');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                self.collectFilterValues();
                self.loadAllWidgets();
            });
        }

        var resetBtn = filterBar.querySelector('.btn-reset-filters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                var inputs = filterBar.querySelectorAll('.filter-input');
                inputs.forEach(function (input) {
                    var def = input.getAttribute('data-default');
                    input.value = def !== null ? def : '';
                });
                if (categorySelect && courseSelect) {
                    handleCategoryChange();
                } else if (courseSelect && groupSelect) {
                    handleCourseChange();
                }
                self.collectFilterValues();
                self.loadAllWidgets();
            });
        }
    };

    /**
     * Load data for all widgets in the dashboard.
     */
    DashboardView.prototype.loadAllWidgets = function () {
        var self = this;
        var widgetSlots = this.root.querySelectorAll('.widget-slot');
        var requests = [];
        var widgetIds = [];

        widgetSlots.forEach(function (slot) {
            var widgetId = parseInt(slot.getAttribute('data-widgetid'), 10);
            if (widgetId) {
                widgetIds.push(widgetId);
                requests.push({
                    methodname: 'local_lms_dashboard_builder_get_widget_data',
                    args: {
                        widgetid: widgetId,
                        contextid: self.contextId,
                        filtersjson: JSON.stringify(self.currentFilters)
                    }
                });
            }
        });

        if (requests.length === 0) {
            return;
        }

        ajax.call(requests).forEach(function (promise, idx) {
            var widgetId = widgetIds[idx];
            promise.then(function (response) {
                self.renderWidget(widgetId, response);
            }).catch(function () {
                self.renderWidgetError(widgetId);
            });
        });
    };

    /**
     * Render widget content based on returned JSON payload and widget type.
     *
     * @param {number} widgetId The widget ID.
     * @param {Object} response The server response.
     */
    DashboardView.prototype.renderWidget = function (widgetId, response) {
        var slot = this.root.querySelector('.widget-slot[data-widgetid="' + widgetId + '"]');
        if (!slot) {
            return;
        }

        var payload = {};
        try {
            payload = JSON.parse(response.datajson);
        } catch (e) {
            payload = {};
        }

        if (!payload.config && response.configjson) {
            try {
                payload.config = JSON.parse(response.configjson);
            } catch (e) {
                payload.config = {};
            }
        }

        var widgetType = response.widgettype;
        var container = slot.querySelector('.widget-container-target') ||
            slot.querySelector('#widget-container-' + widgetId) ||
            slot;

        if (widgetType === 'kpi') {
            this.renderKPI(container, response.title, payload);
        } else if (widgetType === 'chart') {
            this.renderChart(container, response.title, payload, widgetId);
        } else if (widgetType === 'table') {
            this.renderTable(container, response.title, payload, widgetId);
        } else if (widgetType === 'progress') {
            this.renderProgress(container, response.title, payload);
        } else if (widgetType === 'ring') {
            this.renderRing(container, response.title, payload);
        } else if (widgetType === 'comparison') {
            this.renderComparison(container, response.title, payload);
        } else if (widgetType === 'activity_list') {
            this.renderActivityList(container, response.title, payload);
        } else if (widgetType === 'status_distribution') {
            this.renderStatusDistribution(container, response.title, payload);
        } else if (widgetType === 'ranking') {
            this.renderRanking(container, response.title, payload);
        } else if (widgetType === 'text') {
            this.renderText(container, response.title, payload);
        } else {
            this.renderKPI(container, response.title, payload);
        }
    };

    /**
     * Render KPI Metric Card with icon, accent, prefix/suffix, and subtitle.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderKPI = function (container, title, payload) {
        var metrics = payload.metrics || {};
        var cfg = payload.config || {};
        var metricKey = (cfg.metric && metrics[cfg.metric] !== undefined)
            ? cfg.metric
            : Object.keys(metrics)[0];
        var rawVal = metricKey ? metrics[metricKey] : '0';
        var prefix = cfg.prefix || '';
        var suffix = cfg.suffix || '';
        var formattedVal = prefix + rawVal + suffix;
        var subtitle = cfg.subtitle || (metricKey ? metricKey.replace(/_/g, ' ') : '');
        var accent = cfg.accent || 'primary';
        var icon = cfg.icon || 'fa-chart-bar';

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-kpi border-top border-3 border-' +
            escapeHtml(accent) + '">' +
            '<div class="card-body d-flex flex-column justify-content-between p-4">' +
            '<div class="d-flex justify-content-between align-items-start">' +
            '<div>' +
            '<h6 class="card-subtitle text-muted text-uppercase fw-semibold mb-2 fs-7">' + escapeHtml(title) + '</h6>' +
            '<div class="display-6 fw-bold text-dark kpi-value mb-1">' + escapeHtml(formattedVal) + '</div>' +
            '</div>' +
            '<span class="badge bg-' + escapeHtml(accent) + '-subtle text-' + escapeHtml(accent) + ' p-2 rounded-circle">' +
            '<i class="fa ' + escapeHtml(icon) + '" aria-hidden="true"></i>' +
            '</span>' +
            '</div>' +
            '<div class="small text-secondary mt-2 text-capitalize">' + escapeHtml(subtitle) + '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Highly Customizable Chart using Moodle 5.2 core Chart.js (core/chartjs-lazy).
     *
     * Supports 12 chart types: bar, horizontalBar, stackedBar, line, area, combo,
     * pie, doughnut, gauge, radar, polarArea, scatter.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     * @param {number} widgetId The widget ID.
     */
    DashboardView.prototype.renderChart = function (container, title, payload, widgetId) {
        var self = this;
        var chartData = payload.chart || { labels: [], datasets: [] };
        var labels = (chartData.labels || []).slice();
        var rawDatasets = chartData.datasets && chartData.datasets.length ? chartData.datasets : [{ data: [] }];
        var cfg = payload.config || {};
        var chartType = cfg.charttype || 'bar';
        var paletteName = cfg.palette || 'modern';
        var palette = (cfg.customColors && cfg.customColors.length)
            ? cfg.customColors
            : (PALETTES[paletteName] || PALETTES.modern);

        var firstValues = (rawDatasets[0].data || []).slice();
        var accessibleList = labels.map(function (lbl, i) {
            return lbl + ': ' + (firstValues[i] !== undefined ? firstValues[i] : 0);
        }).join(', ');

        var scopedId = this.instanceId + '-' + widgetId;
        var canvasId = 'chart-canvas-' + scopedId;
        var accessibleId = 'chart-accessible-' + scopedId;
        var tableToggleId = 'chart-table-view-' + scopedId;
        var chartHeight = parseInt(cfg.height, 10) || 250;
        var subtitleHtml = cfg.subtitle
            ? '<div class="small text-muted">' + escapeHtml(cfg.subtitle) + '</div>'
            : '';

        // Build accessible HTML table alternative for toggle button.
        var tableRowsHtml = labels.map(function (lbl, idx) {
            var cells = rawDatasets.map(function (ds) {
                var v = ds.data && ds.data[idx] !== undefined ? ds.data[idx] : 0;
                return '<td>' + escapeHtml(v) + (cfg.suffix ? escapeHtml(cfg.suffix) : '') + '</td>';
            }).join('');
            return '<tr><td>' + escapeHtml(lbl) + '</td>' + cells + '</tr>';
        }).join('');
        var tableHeadersHtml = rawDatasets.map(function (ds) {
            return '<th scope="col">' + escapeHtml(ds.label || title) + '</th>';
        }).join('');

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-chart">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0 d-flex justify-content-between align-items-start">' +
            '<div>' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            subtitleHtml +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-link text-muted p-0 btn-toggle-chart-table" ' +
            'title="Toggle Accessible Data Table" aria-controls="' + tableToggleId + '">' +
            '<i class="fa fa-table" aria-hidden="true"></i>' +
            '<span class="visually-hidden">Toggle Data Table</span>' +
            '</button>' +
            '</div>' +
            '<div class="card-body p-4 position-relative">' +
            '<div class="chart-canvas-container" style="position: relative; width: 100%; height: ' + chartHeight + 'px;">' +
            '<canvas id="' + canvasId + '" role="img" ' +
            'aria-label="' + escapeHtml(title) + ': ' + escapeHtml(accessibleList) + '"></canvas>' +
            '</div>' +
            '<div class="chart-table-fallback d-none mt-2 table-responsive" id="' + tableToggleId + '">' +
            '<table class="table table-sm table-bordered mb-0 small">' +
            '<thead class="table-light"><tr><th scope="col">' + escapeHtml(cfg.xAxisLabel || 'Category') + '</th>' +
            tableHeadersHtml + '</tr></thead>' +
            '<tbody>' + tableRowsHtml + '</tbody>' +
            '</table>' +
            '</div>' +
            '<div class="chart-accessible-summary visually-hidden" id="' + accessibleId + '">' +
            '<span>' + escapeHtml(accessibleList) + '</span>' +
            '</div>' +
            '</div>' +
            '</div>';

        var toggleBtn = container.querySelector('.btn-toggle-chart-table');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var tbl = document.getElementById(tableToggleId);
                var cwrap = container.querySelector('.chart-canvas-container');
                if (tbl && cwrap) {
                    tbl.classList.toggle('d-none');
                    cwrap.classList.toggle('d-none');
                }
            });
        }

        if (self.chartInstances[scopedId]) {
            try {
                self.chartInstances[scopedId].destroy();
            } catch (e) {
                // Ignore.
            }
            delete self.chartInstances[scopedId];
        }

        if (labels.length === 0) {
            var emptyWrap = container.querySelector('.chart-canvas-container');
            if (emptyWrap) {
                emptyWrap.innerHTML = '<div class="d-flex h-100 align-items-center justify-content-center text-muted small">' +
                    escapeHtml(cfg.emptyMessage || 'No data available for selected filters.') + '</div>';
            }
            return;
        }

        // Map extended chart types to Chart.js configuration.
        var realType = chartType;
        var indexAxis = 'x';
        var isStacked = false;
        var isArea = false;
        var isGauge = false;
        var isCombo = false;

        if (chartType === 'horizontalBar') {
            realType = 'bar';
            indexAxis = 'y';
        } else if (chartType === 'stackedBar') {
            realType = 'bar';
            isStacked = true;
        } else if (chartType === 'area') {
            realType = 'line';
            isArea = true;
        } else if (chartType === 'gauge') {
            realType = 'doughnut';
            isGauge = true;
        } else if (chartType === 'combo') {
            realType = 'bar';
            isCombo = true;
        }

        var isCircular = (realType === 'pie' || realType === 'doughnut' || realType === 'polarArea' || realType === 'radar');
        var borderRadius = cfg.borderRadius !== undefined ? parseInt(cfg.borderRadius, 10) : 6;
        var lineTension = cfg.lineTension !== undefined ? parseFloat(cfg.lineTension) : 0.3;

        var configuredDatasets = rawDatasets.map(function (ds, dsIdx) {
            var dsColor = palette[dsIdx % palette.length];
            var dsObj = {
                label: ds.label || title,
                data: ds.data || [],
                backgroundColor: (rawDatasets.length === 1 && (isCircular || realType === 'bar')) ? palette : dsColor,
                borderColor: (realType === 'line' || (isCombo && dsIdx > 0)) ? dsColor : (isCircular ? '#ffffff' : undefined),
                borderWidth: (realType === 'line' || isCircular) ? 2 : 0,
                borderRadius: realType === 'bar' ? borderRadius : 0,
                tension: lineTension,
                fill: isArea
            };
            if (isCombo && dsIdx > 0) {
                dsObj.type = 'line';
                dsObj.backgroundColor = dsColor;
            }
            if (realType === 'scatter') {
                dsObj.data = (ds.data || []).map(function (v, idx) {
                    return typeof v === 'object' ? v : { x: idx + 1, y: v };
                });
            }
            return dsObj;
        });

        var legendPos = cfg.legendPosition || (isCircular || rawDatasets.length > 1 ? 'bottom' : 'none');
        var showLegend = legendPos !== 'none';

        require(['core/chartjs-lazy'], function (Chart) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }
            var ctx = canvas.getContext('2d');
            var chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: indexAxis,
                plugins: {
                    legend: {
                        display: showLegend,
                        position: showLegend ? legendPos : 'bottom'
                    },
                    tooltip: {
                        enabled: true
                    }
                }
            };

            if (isGauge) {
                chartOptions.rotation = -90;
                chartOptions.circumference = 180;
                chartOptions.cutout = '68%';
            } else if (!isCircular) {
                chartOptions.scales = {
                    y: {
                        beginAtZero: true,
                        stacked: isStacked,
                        title: {
                            display: Boolean(cfg.yAxisLabel),
                            text: cfg.yAxisLabel || ''
                        },
                        grid: {
                            display: cfg.showGridY !== false,
                            color: '#f3f4f6'
                        }
                    },
                    x: {
                        stacked: isStacked,
                        title: {
                            display: Boolean(cfg.xAxisLabel),
                            text: cfg.xAxisLabel || ''
                        },
                        grid: {
                            display: Boolean(cfg.showGridX)
                        }
                    }
                };
            }

            self.chartInstances[scopedId] = new Chart(ctx, {
                type: realType,
                data: {
                    labels: labels,
                    datasets: configuredDatasets
                },
                options: chartOptions
            });
        }, function () {
            self.drawFallbackCanvas(canvasId, labels, firstValues, palette);
        });
    };

    /**
     * Fallback High-DPI Canvas chart renderer.
     *
     * @param {string} canvasId The canvas element ID.
     * @param {Array} labels Array of category labels.
     * @param {Array} values Array of numeric values.
     * @param {Array} colors Array of color hex/rgb strings.
     */
    DashboardView.prototype.drawFallbackCanvas = function (canvasId, labels, values, colors) {
        setTimeout(function () {
            var canvas = document.getElementById(canvasId);
            if (!canvas || !canvas.getContext) {
                return;
            }
            var ctx = canvas.getContext('2d');
            var dpr = window.devicePixelRatio || 1;
            var rect = canvas.getBoundingClientRect();
            var width = rect.width || 360;
            var height = rect.height || 220;

            canvas.width = width * dpr;
            canvas.height = height * dpr;
            ctx.scale(dpr, dpr);
            ctx.clearRect(0, 0, width, height);

            var count = labels.length;
            if (count === 0) {
                ctx.fillStyle = '#6c757d';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('No data available', width / 2, height / 2);
                return;
            }

            var maxVal = Math.max.apply(null, values.concat([1]));
            var paddingLeft = 40;
            var paddingBottom = 40;
            var paddingTop = 20;
            var paddingRight = 20;
            var chartWidth = width - paddingLeft - paddingRight;
            var chartHeight = height - paddingTop - paddingBottom;
            var barWidth = Math.max(14, Math.min(48, Math.floor((chartWidth / count) * 0.6)));
            var barGap = (chartWidth - (barWidth * count)) / (count + 1);

            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(paddingLeft, height - paddingBottom);
            ctx.lineTo(width - paddingRight, height - paddingBottom);
            ctx.stroke();

            labels.forEach(function (lbl, i) {
                var val = values[i] || 0;
                var barHeight = Math.round((val / maxVal) * (chartHeight - 20));
                var x = paddingLeft + barGap + i * (barWidth + barGap);
                var y = height - paddingBottom - barHeight;
                var col = colors[i % colors.length];

                ctx.fillStyle = col;
                ctx.beginPath();
                ctx.rect(x, y, barWidth, Math.max(2, barHeight));
                ctx.closePath();
                ctx.fill();

                ctx.fillStyle = '#374151';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(String(val), x + (barWidth / 2), Math.max(14, y - 6));

                ctx.fillStyle = '#6b7280';
                ctx.font = '11px sans-serif';
                var displayLbl = lbl.length > 8 ? lbl.substring(0, 7) + '…' : lbl;
                ctx.fillText(displayLbl, x + (barWidth / 2), height - paddingBottom + 18);
            });
        }, 10);
    };

    /**
     * Render Sortable Data Table widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderTable = function (container, title, payload) {
        var tableData = payload.table || { columns: [], rows: [] };
        var columns = tableData.columns || [];
        var rows = tableData.rows || [];

        var thead = columns.map(function (c) {
            return '<th scope="col">' + escapeHtml(c) + '</th>';
        }).join('');

        var tbody = rows.length ? rows.map(function (row) {
            var cells = Object.values(row).map(function (v) {
                return '<td>' + escapeHtml(v) + '</td>';
            }).join('');
            return '<tr>' + cells + '</tr>';
        }).join('') : '<tr><td colspan="' + Math.max(1, columns.length) +
            '" class="text-center text-muted py-3">No records found.</td></tr>';

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-table">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0 d-flex justify-content-between align-items-center">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '<span class="badge bg-light text-muted border">' + rows.length + ' rows</span>' +
            '</div>' +
            '<div class="card-body p-4">' +
            '<div class="table-responsive">' +
            '<table class="table table-hover align-middle mb-0">' +
            '<thead class="table-light"><tr>' + thead + '</tr></thead>' +
            '<tbody>' + tbody + '</tbody>' +
            '</table>' +
            '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Linear Progress Bar widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderProgress = function (container, title, payload) {
        var metrics = payload.metrics || {};
        var cfg = payload.config || {};
        var accent = cfg.accent || 'success';
        var rate = metrics.completion_rate !== undefined ? metrics.completion_rate : (metrics.growth_rate || 0);
        var completed = metrics.completed_count !== undefined ? metrics.completed_count : (metrics.graded_count || 0);
        var total = metrics.total_tracked !== undefined ? metrics.total_tracked :
            (metrics.enrolled_students !== undefined ? metrics.enrolled_students : (metrics.total_submissions || 0));

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-progress">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '</div>' +
            '<div class="card-body p-4 d-flex flex-column justify-content-center">' +
            '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<span class="text-muted small">Completion Status</span>' +
            '<span class="fw-bold text-' + escapeHtml(accent) + ' fs-5">' + escapeHtml(rate) + '%</span>' +
            '</div>' +
            '<div class="progress" style="height: 12px; border-radius: 6px;">' +
            '<div class="progress-bar bg-' + escapeHtml(accent) + '" role="progressbar" style="width: ' +
            Math.min(100, Math.max(0, Number(rate))) + '%;" ' +
            'aria-valuenow="' + escapeHtml(rate) + '" aria-valuemin="0" aria-valuemax="100"></div>' +
            '</div>' +
            '<div class="d-flex justify-content-between text-muted small mt-2">' +
            '<span>Completed: ' + escapeHtml(completed) + '</span>' +
            '<span>Total: ' + escapeHtml(total) + '</span>' +
            '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render SVG Circular Progress Ring widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderRing = function (container, title, payload) {
        var metrics = payload.metrics || {};
        var cfg = payload.config || {};
        var metricKey = cfg.metric || 'completion_rate';
        var pct = metrics[metricKey] !== undefined ? Number(metrics[metricKey]) : (Number(metrics.completion_rate) || 0);
        pct = Math.min(100, Math.max(0, Math.round(pct * 10) / 10));
        var completed = metrics.completed_count !== undefined ? metrics.completed_count : 0;
        var total = metrics.total_tracked !== undefined ? metrics.total_tracked : (metrics.enrolled_students || 0);
        var strokeColor = cfg.accent === 'primary' ? '#3b82f6' : (cfg.accent === 'info' ? '#06b6d4' : '#10b981');
        var circumference = 2 * Math.PI * 42;
        var offset = circumference - (pct / 100) * circumference;

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-ring">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '</div>' +
            '<div class="card-body p-4 d-flex align-items-center justify-content-around">' +
            '<div class="position-relative d-inline-flex align-items-center justify-content-center">' +
            '<svg width="110" height="110" viewBox="0 0 100 100" role="img" aria-label="' + escapeHtml(pct) + '%">' +
            '<circle cx="50" cy="50" r="42" fill="none" stroke="#e5e7eb" stroke-width="10"></circle>' +
            '<circle cx="50" cy="50" r="42" fill="none" stroke="' + strokeColor + '" stroke-width="10" ' +
            'stroke-dasharray="' + circumference.toFixed(1) + '" stroke-dashoffset="' + offset.toFixed(1) + '" ' +
            'stroke-linecap="round" transform="rotate(-90 50 50)"></circle>' +
            '</svg>' +
            '<div class="position-absolute fw-bold fs-5 text-dark">' + escapeHtml(pct) + '%</div>' +
            '</div>' +
            '<div class="small text-muted">' +
            '<div class="mb-1"><strong class="text-dark">' + escapeHtml(completed) + '</strong> Completed</div>' +
            '<div><strong class="text-dark">' + escapeHtml(total) + '</strong> Total Tracked</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Metric Period Comparison widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderComparison = function (container, title, payload) {
        var comp = payload.comparison || {};
        var metrics = payload.metrics || {};
        var current = comp.current !== undefined ? Number(comp.current) : (Number(Object.values(metrics)[0]) || 0);
        var previous = comp.previous !== undefined ? Number(comp.previous) : (Number(Object.values(metrics)[1]) || 0);
        var label = comp.label || 'Current vs Baseline';
        var deltaPct = previous > 0 ? Math.round(((current - previous) / previous) * 100) : 0;
        var badgeClass = deltaPct >= 0 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
        var sign = deltaPct > 0 ? '+' : '';

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-comparison">' +
            '<div class="card-body p-4 d-flex flex-column justify-content-between">' +
            '<div>' +
            '<h6 class="card-subtitle text-muted text-uppercase fw-semibold mb-2 fs-7">' + escapeHtml(title) + '</h6>' +
            '<div class="d-flex align-items-baseline gap-2">' +
            '<span class="display-6 fw-bold text-dark">' + escapeHtml(current) + '</span>' +
            '<span class="text-muted small">/ ' + escapeHtml(previous) + '</span>' +
            '<span class="badge ' + badgeClass + ' ms-auto">' + sign + escapeHtml(deltaPct) + '%</span>' +
            '</div>' +
            '</div>' +
            '<div class="small text-secondary mt-2">' + escapeHtml(label) + '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Upcoming Deadlines & Activity List widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderActivityList = function (container, title, payload) {
        var activities = payload.activities || [];
        var cfg = payload.config || {};
        var limit = parseInt(cfg.limit, 10) || 6;
        var items = activities.slice(0, limit);

        var listHtml = items.length ? items.map(function (item) {
            var badge = item.badge || 'primary';
            return '<li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom">' +
                '<div>' +
                '<div class="fw-semibold text-dark small">' + escapeHtml(item.title) + '</div>' +
                '<div class="text-muted" style="font-size: 0.78rem;">' + escapeHtml(item.course || '') +
                (item.date ? ' • ' + escapeHtml(item.date) : '') + '</div>' +
                '</div>' +
                '<span class="badge bg-' + escapeHtml(badge) + '-subtle text-' + escapeHtml(badge) + '">' +
                escapeHtml(item.status || 'Active') + '</span>' +
                '</li>';
        }).join('') : '<li class="list-group-item px-0 text-muted small">No upcoming activities or deadlines in scope.</li>';

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-activity-list">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '</div>' +
            '<div class="card-body p-4">' +
            '<ul class="list-group list-group-flush mb-0">' + listHtml + '</ul>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Status Distribution multi-segment breakdown widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderStatusDistribution = function (container, title, payload) {
        var chart = payload.chart || { labels: [], datasets: [] };
        var labels = chart.labels || [];
        var values = (chart.datasets && chart.datasets[0] && chart.datasets[0].data) ? chart.datasets[0].data : [];
        var cfg = payload.config || {};
        var palette = PALETTES[cfg.palette || 'emerald'] || PALETTES.modern;
        var total = values.reduce(function (acc, v) {
            return acc + Number(v || 0);
        }, 0);

        var segmentsHtml = labels.map(function (lbl, i) {
            var val = Number(values[i] || 0);
            var pct = total > 0 ? Math.round((val / total) * 100) : 0;
            var col = palette[i % palette.length];
            return '<div class="progress-bar" role="progressbar" style="width: ' + pct + '%; background-color: ' + col + ';" ' +
                'title="' + escapeHtml(lbl) + ': ' + val + ' (' + pct + '%)" aria-valuenow="' + pct +
                '" aria-valuemin="0" aria-valuemax="100"></div>';
        }).join('');

        var legendHtml = labels.map(function (lbl, i) {
            var val = Number(values[i] || 0);
            var pct = total > 0 ? Math.round((val / total) * 100) : 0;
            var col = palette[i % palette.length];
            return '<div class="d-flex align-items-center justify-content-between small mb-1">' +
                '<span><span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:' +
                col + ';"></span>' + escapeHtml(lbl) + '</span>' +
                '<span class="fw-semibold text-dark">' + escapeHtml(val) + ' <span class="text-muted">(' +
                pct + '%)</span></span>' +
                '</div>';
        }).join('');

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-status-distribution">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '</div>' +
            '<div class="card-body p-4 d-flex flex-column justify-content-center">' +
            '<div class="progress mb-3" style="height: 16px; border-radius: 8px;">' + segmentsHtml + '</div>' +
            '<div>' + legendHtml + '</div>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render Top/Bottom Ranking Leaderboard widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderRanking = function (container, title, payload) {
        var chart = payload.chart || { labels: [], datasets: [] };
        var cfg = payload.config || {};
        var limit = parseInt(cfg.limit, 10) || 5;
        var labels = (chart.labels || []).slice(0, limit);
        var values = ((chart.datasets && chart.datasets[0] && chart.datasets[0].data) || []).slice(0, limit);
        var maxVal = Math.max.apply(null, values.concat([1]));

        var rowsHtml = labels.length ? labels.map(function (lbl, idx) {
            var val = Number(values[idx] || 0);
            var pct = Math.round((val / maxVal) * 100);
            return '<div class="mb-3">' +
                '<div class="d-flex justify-content-between align-items-center small mb-1">' +
                '<span class="fw-semibold text-dark"><span class="badge bg-light text-dark border me-2">#' +
                (idx + 1) + '</span>' + escapeHtml(lbl) + '</span>' +
                '<span class="fw-bold text-primary">' + escapeHtml(val) + '</span>' +
                '</div>' +
                '<div class="progress" style="height: 6px;">' +
                '<div class="progress-bar bg-primary" style="width: ' + pct + '%;"></div>' +
                '</div>' +
                '</div>';
        }).join('') : '<div class="text-muted small">No ranking data available.</div>';

        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-white widget-container widget-ranking">' +
            '<div class="card-header bg-transparent border-0 pt-3 px-4 pb-0">' +
            '<h6 class="card-title fw-bold text-dark mb-0">' + escapeHtml(title) + '</h6>' +
            '</div>' +
            '<div class="card-body p-4">' + rowsHtml + '</div>' +
            '</div>';
    };

    /**
     * Render Summary / Text banner widget.
     *
     * @param {HTMLElement} container The DOM element container.
     * @param {string} title Widget title.
     * @param {Object} payload Query payload data.
     */
    DashboardView.prototype.renderText = function (container, title, payload) {
        var cfg = payload.config || {};
        var bodyText = payload.text || cfg.content || '';
        container.innerHTML =
            '<div class="card h-100 shadow-sm border-0 bg-light widget-container widget-text">' +
            '<div class="card-body p-4">' +
            '<h6 class="fw-bold text-dark mb-2">' + escapeHtml(title) + '</h6>' +
            '<p class="text-muted small mb-0">' + escapeHtml(bodyText) + '</p>' +
            '</div>' +
            '</div>';
    };

    /**
     * Render error fallback on failure.
     *
     * @param {number} widgetId The widget ID.
     */
    DashboardView.prototype.renderWidgetError = function (widgetId) {
        var slot = this.root.querySelector('.widget-slot[data-widgetid="' + widgetId + '"]');
        if (!slot) {
            return;
        }
        var container = slot.querySelector('.widget-container-target') ||
            slot.querySelector('#widget-container-' + widgetId) ||
            slot;
        container.innerHTML =
            '<div class="alert alert-warning mb-0 p-3 small">' +
            '<span>Failed to load widget data.</span>' +
            '</div>';
    };

    /**
     * Escape HTML characters to prevent XSS.
     *
     * @param {*} text Raw input text.
     * @return {string} Escaped HTML string.
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        var str = String(text);
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    return {
        /**
         * Initialize a new DashboardView instance.
         *
         * @param {number} dashboardId Dashboard ID.
         * @param {number} contextId Context ID.
         * @param {Object} initialFilters Initial filter state.
         * @param {string} [instanceId] Unique placement instance ID.
         * @return {DashboardView} Controller instance.
         */
        init: function (dashboardId, contextId, initialFilters, instanceId) {
            return new DashboardView(dashboardId, contextId, initialFilters, instanceId);
        }
    };
});
