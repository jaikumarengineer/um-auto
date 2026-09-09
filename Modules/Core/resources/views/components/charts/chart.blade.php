@props([
    'type' => 'line',
    'dataset' => ['labels' => [], 'datasets' => []],
    'options' => [],
    'height' => 260,
    'autoColor' => true,
])

{{--
    Generic Chart.js wrapper. Pass a Chart.js-shaped `dataset` (labels + datasets)
    and it renders + themes itself automatically using the active admin color
    preset. No JS wiring needed per page — just drop it in and pass data.

    <x-core::charts.chart
        type="line"
        :dataset="['labels' => ['Mon', 'Tue'], 'datasets' => [['label' => 'Users', 'data' => [10, 20]]]]"
    />
--}}
<div
    wire:ignore
    x-data="{
        chart: null,
        init() {
            window.onAdminFormsReady(() => this.setup());
        },
        setup() {
            const api = window.AdminCharts;
            if (! api || ! window.AdminForms?.Chart) return;

            const data = @js($dataset);
            const palette = api.palette();
            const swatches = [palette.primary, palette.secondary, '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];

            if (@js($autoColor)) {
                (data.datasets || []).forEach((set, index) => {
                    const color = swatches[index % swatches.length];
                    const isFillType = @js(in_array($type, ['line', 'radar'], true));

                    if (! set.borderColor) set.borderColor = color;
                    if (! set.backgroundColor) {
                        set.backgroundColor = @js($type) === 'doughnut' || @js($type) === 'pie'
                            ? swatches
                            : (isFillType ? color + '26' : color);
                    }
                    if (@js($type) === 'line' && set.tension === undefined) set.tension = 0.35;
                    if (@js($type) === 'line' && set.fill === undefined) set.fill = true;
                    if (@js($type) === 'line' && set.pointRadius === undefined) set.pointRadius = 0;
                    if (@js($type) === 'bar' && set.borderRadius === undefined) set.borderRadius = 6;
                    if (@js($type) === 'bar' && set.borderWidth === undefined) set.borderWidth = 0;
                    if (@js($type) === 'bar' && set.maxBarThickness === undefined) set.maxBarThickness = 28;
                });
            }

            const isDonutLike = ['doughnut', 'pie', 'polarArea'].includes(@js($type));
            const options = api.baseOptions(@js($options));

            if (isDonutLike) {
                delete options.scales;
            }

            this.chart = new window.AdminForms.Chart(this.$refs.canvas, {
                type: @js($type),
                data,
                options,
            });

            this._onAppearance = () => {
                // Guard against Chart.js's well-known 'destroy-during-animation'
                // crash: if a pending animation frame is still running when we
                // destroy() the old chart, its now-null ctx gets drawn on and
                // throws mid-frame — which also aborts every *other* chart's
                // draw callback batched in that same animation frame. stop()
                // cancels any in-flight animation before we tear it down.
                if (! this.chart || ! this.$refs.canvas?.isConnected) return;
                this.chart.stop();
                this.chart.destroy();
                this.chart = new window.AdminForms.Chart(this.$refs.canvas, {
                    type: @js($type),
                    data,
                    options: isDonutLike ? (() => { const o = api.baseOptions(@js($options)); delete o.scales; return o; })() : api.baseOptions(@js($options)),
                });
            };
            window.addEventListener('admin-theme-changed', this._onAppearance);
        },
        destroy() {
            window.removeEventListener('admin-theme-changed', this._onAppearance);
            this.chart?.stop();
            this.chart?.destroy();
            this.chart = null;
        }
    }"
    x-on:remove="destroy()"
    style="height: {{ is_numeric($height) ? $height.'px' : $height }};"
>
    <canvas x-ref="canvas"></canvas>
</div>
