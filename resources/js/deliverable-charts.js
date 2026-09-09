import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
    Filler
);

const initializedBurnUps = new WeakSet();

function bucketInRange(bucket, fromValue, toValue) {
    if (!fromValue && !toValue) {
        return true;
    }

    const start = bucket.start;
    const end = bucket.end;

    if (fromValue && end < fromValue) {
        return false;
    }

    if (toValue && start > toValue) {
        return false;
    }

    return true;
}

function initActivityHistogram() {
    const canvas = document.getElementById('deliverable-activity-histogram');
    if (!canvas) {
        return;
    }

    const payload = JSON.parse(canvas.dataset.activityBuckets || '{"buckets":[]}');
    const buckets = payload.buckets || [];
    if (buckets.length === 0) {
        return;
    }

    const fromInput = document.getElementById('deliverable-from');
    const toInput = document.getElementById('deliverable-to');
    const filterForm = document.getElementById('deliverable-date-filter');
    const selectedFrom = canvas.dataset.selectedFrom || '';
    const selectedTo = canvas.dataset.selectedTo || '';

    let selectionStartIndex = null;
    let selectionEndIndex = null;
    let rangeAnchor = null;

    if (selectedFrom || selectedTo) {
        buckets.forEach((bucket, index) => {
            if (bucketInRange(bucket, selectedFrom, selectedTo)) {
                if (selectionStartIndex === null) {
                    selectionStartIndex = index;
                }
                selectionEndIndex = index;
            }
        });
    }

    function updateSelectionColors() {
        chart.data.datasets[0].backgroundColor = buckets.map((_, bucketIndex) => (
            selectionStartIndex !== null
            && selectionEndIndex !== null
            && bucketIndex >= selectionStartIndex
            && bucketIndex <= selectionEndIndex
                ? 'rgba(13, 110, 253, 0.85)'
                : 'rgba(13, 110, 253, 0.25)'
        ));
        chart.update('none');
    }

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: buckets.map((bucket) => bucket.label),
            datasets: [{
                label: 'Logged activities',
                data: buckets.map((bucket) => bucket.count),
                backgroundColor: buckets.map((_, index) => {
                    if (selectionStartIndex === null || selectionEndIndex === null) {
                        return 'rgba(13, 110, 253, 0.55)';
                    }

                    return index >= selectionStartIndex && index <= selectionEndIndex
                        ? 'rgba(13, 110, 253, 0.85)'
                        : 'rgba(13, 110, 253, 0.25)';
                }),
                borderRadius: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        afterLabel(context) {
                            const bucket = buckets[context.dataIndex];
                            return bucket ? `${bucket.start} to ${bucket.end}` : '';
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                },
            },
            onClick(_event, elements) {
                if (!elements.length || !fromInput || !toInput) {
                    return;
                }

                const index = elements[0].index;
                const bucket = buckets[index];
                if (!bucket) {
                    return;
                }

                if (rangeAnchor === null) {
                    rangeAnchor = index;
                    selectionStartIndex = index;
                    selectionEndIndex = index;
                    fromInput.value = bucket.start;
                    toInput.value = bucket.end;
                    updateSelectionColors();
                    return;
                }

                selectionStartIndex = Math.min(rangeAnchor, index);
                selectionEndIndex = Math.max(rangeAnchor, index);
                fromInput.value = buckets[selectionStartIndex].start;
                toInput.value = buckets[selectionEndIndex].end;
                rangeAnchor = null;
                updateSelectionColors();

                if (filterForm) {
                    filterForm.requestSubmit();
                }
            },
        },
    });
}

function initBurnUpChart(canvas) {
    if (!canvas || initializedBurnUps.has(canvas)) {
        return;
    }

    const payload = JSON.parse(canvas.dataset.burnUp || '{"buckets":[]}');
    const buckets = payload.buckets || [];
    if (buckets.length === 0) {
        return;
    }

    initializedBurnUps.add(canvas);

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: buckets.map((bucket) => bucket.label),
            datasets: [
                {
                    label: 'Cumulative progress',
                    data: buckets.map((bucket) => bucket.cumulative),
                    borderColor: 'rgba(25, 135, 84, 1)',
                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                    fill: true,
                    tension: 0.15,
                    pointRadius: 2,
                },
                {
                    label: 'Expected pace',
                    data: buckets.map((bucket) => bucket.pace),
                    borderColor: 'rgba(108, 117, 125, 0.9)',
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                },
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
}

function initBurnUpChartsIn(container) {
    container.querySelectorAll('[data-burn-up-chart]').forEach((canvas) => {
        initBurnUpChart(canvas);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initActivityHistogram();

    document.querySelectorAll('.collapse[data-burn-up]').forEach((collapseEl) => {
        collapseEl.addEventListener('shown.bs.collapse', () => {
            initBurnUpChartsIn(collapseEl);
        });
    });
});
