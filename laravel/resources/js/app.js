import './bootstrap';
import flatpickr from 'flatpickr';

const pairSearch = document.querySelector('[data-pair-search]');
const pairSelect = document.querySelector('[data-pair-select]');

if (pairSearch instanceof HTMLInputElement && pairSelect instanceof HTMLSelectElement) {
    pairSearch.addEventListener('input', () => {
        const query = pairSearch.value.trim().toLocaleLowerCase();
        for (const option of pairSelect.options) {
            option.hidden = option.value !== '' && !(option.textContent ?? '').toLocaleLowerCase().includes(query);
        }
    });
}

document.querySelectorAll('[data-flatpickr]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const pickerType = input.dataset.flatpickr;
    const isDateTime = pickerType === 'datetime';
    const isTime = pickerType === 'time';

    flatpickr(input, {
        allowInput: true,
        altInput: true,
        altInputClass: input.className,
        altFormat: isTime ? 'H:i' : isDateTime ? 'j F Y H:i' : 'j F Y',
        dateFormat: isTime ? 'H:i' : isDateTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
        disableMobile: true,
        enableTime: isDateTime || isTime,
        minuteIncrement: 1,
        noCalendar: isTime,
        time_24hr: true,
    });
});

const fullDayToggle = document.querySelector('#full_day');

if (fullDayToggle instanceof HTMLInputElement) {
    const timeFields = [
        document.querySelector('#active_from'),
        document.querySelector('#active_until'),
    ];

    const updateTimeFields = () => {
        for (const field of timeFields) {
            if (field instanceof HTMLInputElement) {
                field.disabled = fullDayToggle.checked;
                if (field._flatpickr?.altInput) {
                    field._flatpickr.altInput.disabled = fullDayToggle.checked;
                }
            }
        }
    };

    fullDayToggle.addEventListener('change', updateTimeFields);
    updateTimeFields();
}

const marketChart = document.querySelector('[data-market-chart]');
const runStrategyButton = document.querySelector('[data-run-strategy]');

if (runStrategyButton instanceof HTMLButtonElement) {
    runStrategyButton.addEventListener('click', async () => {
        const filters = document.querySelector('[data-exploration-filters]');
        const status = document.querySelector('[data-strategy-status]');
        const summary = document.querySelector('[data-strategy-summary]');

        if (!(filters instanceof HTMLFormElement) || !(status instanceof HTMLElement)) {
            return;
        }

        runStrategyButton.disabled = true;
        runStrategyButton.textContent = 'Running...';
        status.textContent = '';
        if (summary instanceof HTMLElement) {
            summary.hidden = true;
        }
        if (marketChart instanceof HTMLCanvasElement) {
            marketChart.dispatchEvent(new CustomEvent('strategy-results', { detail: [] }));
        }

        try {
            const response = await fetch(runStrategyButton.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(filters)),
            });
            const result = await response.json();

            if (!response.ok) {
                const validationErrors = Object.values(result.errors ?? {}).flat();
                throw new Error(validationErrors.join(' ') || result.message || 'The strategy could not be run.');
            }

            const counts = result.summary;
            for (const [selector, value] of [
                ['[data-strategy-trades]', counts.trades],
                ['[data-strategy-wins]', counts.wins],
                ['[data-strategy-losses]', counts.losses],
                ['[data-strategy-closed]', counts.closed_trades],
            ]) {
                const output = document.querySelector(selector);
                if (output instanceof HTMLElement) {
                    output.textContent = String(value);
                }
            }

            if (summary instanceof HTMLElement) {
                summary.hidden = false;
            }
            status.textContent = `${counts.trades} trades found. Entry and exit positions are marked on the chart.`;
            if (marketChart instanceof HTMLCanvasElement) {
                marketChart.dispatchEvent(new CustomEvent('strategy-results', { detail: result.trades }));
            }
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'The strategy could not be run.';
        } finally {
            runStrategyButton.disabled = false;
            runStrategyButton.innerHTML = 'Run strategy <span>→</span>';
        }
    });
}

if (marketChart instanceof HTMLCanvasElement) {
    const scrollContainer = marketChart.closest('[data-chart-scroll]');
    const track = marketChart.closest('[data-chart-track]');
    const readout = document.querySelector('[data-chart-readout]');
    const candles = JSON.parse(marketChart.dataset.candles ?? '[]');
    const chartHeight = 480;

    if (scrollContainer instanceof HTMLElement && track instanceof HTMLElement && Array.isArray(candles)) {
        const candleIndexes = new Map(candles.map((candle, index) => [candle.time, index]));
        let strategyMarkers = [];
        let candleStep = 14;

        const drawStrategyMarkers = (context, firstIndex, lastIndex, priceY, scrollLeft) => {
            for (const marker of strategyMarkers) {
                if (marker.index < firstIndex || marker.index >= lastIndex) {
                    continue;
                }

                const x = marker.index * candleStep - scrollLeft + candleStep / 2;
                const y = priceY(marker.price);
                const markerLabel = marker.kind === 'entry' ? 'E' : 'C';

                context.save();
                context.shadowColor = 'rgba(22, 32, 27, 0.55)';
                context.shadowBlur = 7;
                context.shadowOffsetY = 2;
                context.beginPath();
                context.arc(x, y, 11, 0, Math.PI * 2);
                context.fillStyle = '#ffffff';
                context.fill();
                context.lineWidth = 2;
                context.strokeStyle = '#17251d';
                context.stroke();
                context.shadowColor = 'transparent';
                context.fillStyle = marker.color;
                context.beginPath();
                if (marker.kind === 'entry') {
                    context.arc(x, y, 8, 0, Math.PI * 2);
                } else {
                    context.roundRect(x - 7, y - 7, 14, 14, 2);
                }
                context.fill();
                context.fillStyle = marker.kind === 'entry' ? '#302800' : '#ffffff';
                context.font = 'bold 10px "DM Mono", monospace';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText(markerLabel, x, y + 0.5);

                const directionArrow = marker.direction === 'long' ? '↑' : '↓';
                context.beginPath();
                context.arc(x + 8, y - 8, 7, 0, Math.PI * 2);
                context.fillStyle = '#17251d';
                context.fill();
                context.fillStyle = '#ffffff';
                context.font = 'bold 10px sans-serif';
                context.fillText(directionArrow, x + 8, y - 7.5);
                context.restore();
            }
        };

        const resizeAndDraw = () => {
            const width = scrollContainer.clientWidth;
            const pixelRatio = window.devicePixelRatio || 1;
            candleStep = candles.length > 0 ? Math.max(14, width / candles.length) : 14;
            track.style.width = `${Math.max(width, candles.length * candleStep)}px`;
            marketChart.width = Math.max(1, Math.floor(width * pixelRatio));
            marketChart.height = Math.floor(chartHeight * pixelRatio);
            marketChart.style.width = `${width}px`;
            marketChart.style.height = `${chartHeight}px`;

            const context = marketChart.getContext('2d');
            if (!context || candles.length === 0) {
                return;
            }

            context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
            context.clearRect(0, 0, width, chartHeight);
            const firstIndex = Math.max(0, Math.floor(scrollContainer.scrollLeft / candleStep));
            const lastIndex = Math.min(candles.length, Math.ceil((scrollContainer.scrollLeft + width) / candleStep) + 1);
            let minPrice = Number.POSITIVE_INFINITY;
            let maxPrice = Number.NEGATIVE_INFINITY;

            for (let index = firstIndex; index < lastIndex; index += 1) {
                minPrice = Math.min(minPrice, candles[index].low);
                maxPrice = Math.max(maxPrice, candles[index].high);
            }
            for (const marker of strategyMarkers) {
                if (marker.index >= firstIndex && marker.index < lastIndex) {
                    minPrice = Math.min(minPrice, marker.price);
                    maxPrice = Math.max(maxPrice, marker.price);
                }
            }

            const rawRange = maxPrice - minPrice || Math.max(Math.abs(maxPrice) * 0.001, 0.000001);
            const padding = rawRange * 0.08;
            minPrice -= padding;
            maxPrice += padding;
            const range = maxPrice - minPrice;
            const plotTop = 20;
            const plotBottom = chartHeight - 34;
            const plotHeight = plotBottom - plotTop;
            const priceY = (price) => plotTop + ((maxPrice - price) / range) * plotHeight;

            context.font = '10px "DM Mono", monospace';
            context.textBaseline = 'middle';
            context.strokeStyle = '#e8ece6';
            context.fillStyle = '#7f8d82';
            context.lineWidth = 1;

            for (let line = 0; line <= 4; line += 1) {
                const y = plotTop + (plotHeight * line) / 4;
                const price = maxPrice - (range * line) / 4;
                context.beginPath();
                context.moveTo(0, y);
                context.lineTo(width, y);
                context.stroke();
                context.fillText(price.toFixed(6), 8, Math.max(10, y - 9));
            }

            for (let index = firstIndex; index < lastIndex; index += 1) {
                const candle = candles[index];
                const x = index * candleStep - scrollContainer.scrollLeft + candleStep / 2;
                const rising = candle.close >= candle.open;
                const color = rising ? '#4b8b59' : '#c66b5b';
                const openY = priceY(candle.open);
                const closeY = priceY(candle.close);

                context.strokeStyle = color;
                context.fillStyle = color;
                context.lineWidth = 1;
                context.beginPath();
                context.moveTo(Math.round(x) + 0.5, priceY(candle.high));
                context.lineTo(Math.round(x) + 0.5, priceY(candle.low));
                context.stroke();
                const bodyWidth = Math.min(8, candleStep * 0.6);
                context.fillRect(x - bodyWidth / 2, Math.min(openY, closeY), bodyWidth, Math.max(1, Math.abs(closeY - openY)));

                if (index % Math.max(1, Math.ceil(110 / candleStep)) === 0) {
                    context.fillStyle = '#7f8d82';
                    context.textAlign = 'center';
                    context.fillText(candle.time, x, chartHeight - 15);
                }
            }

            drawStrategyMarkers(context, firstIndex, lastIndex, priceY, scrollContainer.scrollLeft);
        };

        marketChart.addEventListener('strategy-results', (event) => {
            strategyMarkers = [];
            for (const trade of event.detail) {
                const entryColor = '#f2c400';
                for (const fill of trade.entry_fills) {
                    const index = candleIndexes.get(fill.date.slice(0, 16));
                    if (index !== undefined) {
                        strategyMarkers.push({ index, price: fill.price, color: entryColor, kind: 'entry', direction: trade.signal });
                    }
                }
                for (const fill of trade.exit_fills) {
                    const index = candleIndexes.get(fill.date.slice(0, 16));
                    if (index !== undefined) {
                        strategyMarkers.push({ index, price: fill.price, color: '#2563eb', kind: 'exit', direction: trade.signal });
                    }
                }
            }

            resizeAndDraw();
        });

        scrollContainer.addEventListener('scroll', resizeAndDraw, { passive: true });
        window.addEventListener('resize', resizeAndDraw);
        marketChart.addEventListener('mousemove', (event) => {
            if (!(event instanceof MouseEvent) || !(readout instanceof HTMLElement)) {
                return;
            }

            const bounds = marketChart.getBoundingClientRect();
            const index = Math.floor((event.clientX - bounds.left + scrollContainer.scrollLeft) / candleStep);
            const candle = candles[index];
            if (candle) {
                readout.textContent = `${candle.time}  O ${candle.open.toFixed(6)}  H ${candle.high.toFixed(6)}  L ${candle.low.toFixed(6)}  C ${candle.close.toFixed(6)}`;
            }
        });

        let dragStartX = null;
        let dragScrollLeft = null;
        scrollContainer.addEventListener('pointerdown', (event) => {
            dragStartX = event.clientX;
            dragScrollLeft = scrollContainer.scrollLeft;
            scrollContainer.setPointerCapture(event.pointerId);
        });
        scrollContainer.addEventListener('pointermove', (event) => {
            if (dragStartX !== null && dragScrollLeft !== null && event.buttons > 0) {
                scrollContainer.scrollLeft = dragScrollLeft + dragStartX - event.clientX;
            }
        });
        scrollContainer.addEventListener('pointerup', () => {
            dragStartX = null;
            dragScrollLeft = null;
        });

        resizeAndDraw();
        scrollContainer.scrollLeft = scrollContainer.scrollWidth - scrollContainer.clientWidth;
    }
}
