document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[novalidate]');
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    if (window.__revenueData && document.getElementById('revenueChart')) {
        const labels = window.__revenueData.map(item => item.month);
        const values = window.__revenueData.map(item => Number(item.total));

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Doanh thu',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.15)',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }
});
