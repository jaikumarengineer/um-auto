@props([
    'type' => 'line',
    'dataset' => ['labels' => [], 'datasets' => []],
    'options' => [],
    'height' => 260,
    'autoColor' => true,
    'showValues' => false,
    'valuePosition' => 'inside',
])

{{--
    Generic Chart.js wrapper. Pass a Chart.js-shaped `dataset` (labels + datasets)
    and it renders + themes itself automatically using the active admin color
    preset. No JS wiring needed per page — just drop it in and pass data.

    When the dataset/type changes (filters, tabs), remount via a parent wire:key:

    <div wire:key="po-chart-{{ $period }}">
        <x-core::charts.chart type="line" :dataset="$dataset" />
    </div>
--}}
<div
    wire:ignore
    {{ $attributes }}
    x-data="{
        chart: null,
        _onAppearance: null,
        _retry: null,
        init() {
            window.onAdminFormsReady(() => {
                // Defer until Alpine has bound $refs and Livewire has finished
                // inserting the remounted node — otherwise Chart.js paints a
                // 0×0 canvas and the chart looks blank after filter switches.
                this.$nextTick(() => {
                    requestAnimationFrame(() => this.setup());
                });
            });
        },
        setup(attempt = 0) {
            const api = window.AdminCharts;
            const Chart = window.AdminForms?.Chart;
            const canvas = this.$refs.canvas;

            if (! api || ! Chart || ! canvas) {
                if (attempt < 10) {
                    this._retry = requestAnimationFrame(() => this.setup(attempt + 1));
                }
                return;
            }

            if (! canvas.isConnected || canvas.clientWidth === 0) {
                if (attempt < 20) {
                    this._retry = requestAnimationFrame(() => this.setup(attempt + 1));
                }
                return;
            }

            this.teardown(false);

            const data = structuredClone(@js($dataset));
            const palette = api.palette();
            const swatches = [palette.primary, palette.secondary, '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];
            const chartType = @js($type);
            const autoColor = @js($autoColor);
            const showValues = @js((bool) $showValues);
            const valuePosition = @js($valuePosition === 'above' ? 'above' : 'inside');

            if (autoColor) {
                (data.datasets || []).forEach((set, index) => {
                    const color = swatches[index % swatches.length];
                    const isFillType = ['line', 'radar'].includes(chartType);

                    if (! set.borderColor) set.borderColor = color;
                    if (! set.backgroundColor) {
                        set.backgroundColor = chartType === 'doughnut' || chartType === 'pie'
                            ? swatches
                            : (isFillType ? color + '26' : color);
                    }
                    if (chartType === 'line' && set.tension === undefined) set.tension = 0.35;
                    if (chartType === 'line' && set.fill === undefined) set.fill = true;
                    if (chartType === 'line' && set.pointRadius === undefined) set.pointRadius = 0;
                    if (chartType === 'bar' && set.borderRadius === undefined) set.borderRadius = 6;
                    if (chartType === 'bar' && set.borderWidth === undefined) set.borderWidth = 0;
                    if (chartType === 'bar' && set.maxBarThickness === undefined) set.maxBarThickness = showValues ? 64 : 28;
                });
            }

            const isDonutLike = ['doughnut', 'pie', 'polarArea'].includes(chartType);
            const options = api.baseOptions(@js($options));
            const plugins = [];

            if (isDonutLike) {
                delete options.scales;
            }

            if (showValues && chartType === 'bar') {
                options.layout = options.layout || {};
                options.layout.padding = Object.assign({ top: valuePosition === 'above' ? 22 : 16 }, options.layout.padding || {});
                if (valuePosition === 'above' && options.scales && options.scales.y) {
                    options.scales.y.grace = options.scales.y.grace || '20%';
                }
                plugins.push({
                    id: 'barValueLabels',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;
                        const labelColor = palette.text;

                        chart.data.datasets.forEach((dataset, datasetIndex) => {
                            const meta = chart.getDatasetMeta(datasetIndex);
                            if (! meta || meta.hidden || meta.type !== 'bar') return;

                            meta.data.forEach((bar, index) => {
                                const raw = dataset.data[index];
                                const value = raw && typeof raw === 'object' ? (raw.y ?? raw.x) : raw;
                                const number = Number(value);
                                if (! Number.isFinite(number) || number === 0) return;

                                const text = number.toLocaleString(undefined, { maximumFractionDigits: 3 });
                                const props = bar.getProps(['x', 'y', 'base', 'width', 'height', 'horizontal'], true);
                                const horizontal = !! props.horizontal;
                                const barLength = Math.abs((horizontal ? props.x : props.y) - props.base);
                                const barThickness = Math.abs(horizontal ? props.height : props.width);

                                ctx.save();
                                ctx.font = '600 12px ui-sans-serif, system-ui, sans-serif';

                                if (valuePosition === 'above' && ! horizontal) {
                                    ctx.fillStyle = labelColor;
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'bottom';
                                    ctx.fillText(text, props.x, props.y - 4);
                                    ctx.restore();
                                    return;
                                }

                                let textWidth = ctx.measureText(text).width;
                                if (textWidth > barThickness - 6) {
                                    ctx.font = '600 10px ui-sans-serif, system-ui, sans-serif';
                                    textWidth = ctx.measureText(text).width;
                                }

                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';

                                const fitsAcross = textWidth <= (horizontal ? barLength : barThickness) - 6;
                                const fitsAlong = (horizontal ? barThickness : barLength) >= 16;

                                if (fitsAcross && fitsAlong) {
                                    ctx.fillStyle = '#ffffff';
                                    const midX = horizontal ? props.base + (props.x - props.base) / 2 : props.x;
                                    const midY = horizontal ? props.y : props.y + (props.base - props.y) / 2;
                                    ctx.fillText(text, midX, midY);
                                } else if (! horizontal && barLength >= textWidth + 8 && barThickness >= 14) {
                                    ctx.fillStyle = '#ffffff';
                                    ctx.translate(props.x, props.y + (props.base - props.y) / 2);
                                    ctx.rotate(-Math.PI / 2);
                                    ctx.fillText(text, 0, 0);
                                } else if (! horizontal) {
                                    ctx.fillStyle = labelColor;
                                    ctx.fillText(text, props.x, Math.max(chart.chartArea.top + 8, props.y - 10));
                                } else {
                                    ctx.fillStyle = labelColor;
                                    ctx.textAlign = 'left';
                                    ctx.fillText(text, props.x + 4, props.y);
                                }

                                ctx.restore();
                            });
                        });
                    },
                });
            }

            this.chart = new Chart(canvas, {
                type: chartType,
                data,
                options,
                plugins,
            });

            this.chart.resize();

            this._onAppearance = () => {
                if (! this.$refs.canvas?.isConnected) return;
                this.teardown(false);
                this.setup();
            };
            window.addEventListener('admin-theme-changed', this._onAppearance);
        },
        teardown(cancelRetry = true) {
            if (cancelRetry && this._retry) {
                cancelAnimationFrame(this._retry);
                this._retry = null;
            }

            if (this._onAppearance) {
                window.removeEventListener('admin-theme-changed', this._onAppearance);
                this._onAppearance = null;
            }

            const canvas = this.$refs?.canvas;
            const Chart = window.AdminForms?.Chart;
            const existing = canvas && Chart?.getChart ? Chart.getChart(canvas) : null;

            try { existing?.stop?.(); existing?.destroy?.(); } catch (e) {}
            try { this.chart?.stop?.(); this.chart?.destroy?.(); } catch (e) {}

            this.chart = null;
        },
        destroy() {
            this.teardown(true);
        }
    }"
    x-on:remove="destroy()"
    style="height: {{ is_numeric($height) ? $height.'px' : $height }};"
>
    <canvas x-ref="canvas"></canvas>
</div>
