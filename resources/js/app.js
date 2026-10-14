import './bootstrap';
import flatpickr from 'flatpickr';

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

if (marketChart instanceof HTMLCanvasElement) {
    const scrollContainer = marketChart.closest('[data-chart-scroll]');
    const track = marketChart.closest('[data-chart-track]');
    const readout = document.querySelector('[data-chart-readout]');
    const candles = JSON.parse(marketChart.dataset.candles ?? '[]');
    const chartHeight = 480;

    if (scrollContainer instanceof HTMLElement && track instanceof HTMLElement && Array.isArray(candles)) {
        let candleStep = 14;
        let minPrice = Number.POSITIVE_INFINITY;
        let maxPrice = Number.NEGATIVE_INFINITY;
        for (const candle of candles) {
            minPrice = Math.min(minPrice, candle.low);
            maxPrice = Math.max(maxPrice, candle.high);
        }

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
            const range = maxPrice - minPrice || Math.max(Math.abs(maxPrice) * 0.001, 0.000001);
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

            const firstIndex = Math.max(0, Math.floor(scrollContainer.scrollLeft / candleStep));
            const lastIndex = Math.min(candles.length, Math.ceil((scrollContainer.scrollLeft + width) / candleStep) + 1);
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
        };

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
