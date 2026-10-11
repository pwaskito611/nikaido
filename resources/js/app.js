import './bootstrap';

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
            }
        }
    };

    fullDayToggle.addEventListener('change', updateTimeFields);
    updateTimeFields();
}
