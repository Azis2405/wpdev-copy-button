/**
 * WPDev Admin Charts
 *
 * Merender grafik analitik di dasbor admin menggunakan Chart.js.
 * Versi 2.1 (Tooltip dinamis untuk semua chart)
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof wpdev_chart_data === 'undefined') {
        console.error('WPDev Chart Data not found.');
        return;
    }

    const { deviceData, topPagesData, topTaxonomiesData, topUserGroupsData } = wpdev_chart_data;

    /**
     * Helper function untuk membuat konfigurasi tooltip yang cerdas.
     * Menampilkan judul dari data lengkap jika tersedia.
     * @param {object} chartData Objek data yang berisi 'labels', 'values', dan opsional 'full_paths' atau 'full_labels'.
     * @returns {object} Konfigurasi tooltip.
     */
    const createTooltipConfig = (chartData) => ({
        callbacks: {
            title: function(tooltipItems) {
                const dataIndex = tooltipItems[0].dataIndex;
                // Cek apakah 'full_paths' atau 'full_labels' ada di data. Gunakan yang ada.
                const fullLabels = chartData.full_paths || chartData.full_labels;
                if (fullLabels && typeof fullLabels[dataIndex] !== 'undefined') {
                    return fullLabels[dataIndex];
                }
                // Jika tidak ada, gunakan 'labels' seperti biasa.
                return chartData.labels[dataIndex] || '';
            },
            label: function(tooltipItem) {
                return `Jumlah Salinan: ${tooltipItem.formattedValue}`;
            }
        }
    });

    /**
     * Inisialisasi Grafik Batang Top 10 Halaman
     */
    const topPagesChartCtx = document.getElementById('wpdevTopPagesChart');
    if (topPagesChartCtx && topPagesData) {
        new Chart(topPagesChartCtx, {
            type: 'bar',
            data: {
                labels: topPagesData.labels, // Label sumbu tetap yang pendek
                datasets: [{
                    label: 'Jumlah Salinan',
                    data: topPagesData.values,
                    backgroundColor: '#8B3DFF',
                    borderColor: '#7731D8',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Top 10 Halaman Paling Disalin' },
                    // Gunakan konfigurasi tooltip yang baru
                    tooltip: createTooltipConfig(topPagesData)
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    /**
     * Inisialisasi Grafik Batang Top 10 Taksonomi
     */
    const topTaxonomiesChartCtx = document.getElementById('wpdevTopTaxonomiesChart');
    if (topTaxonomiesChartCtx && topTaxonomiesData) {
        new Chart(topTaxonomiesChartCtx, {
            type: 'bar',
            data: {
                labels: topTaxonomiesData.labels,
                datasets: [{
                    label: 'Jumlah Salinan',
                    data: topTaxonomiesData.values,
                    backgroundColor: '#F36A6A',
                    borderColor: '#D95353',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Top 10 Kategori/Taksonomi' }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    /**
     * Inisialisasi Grafik Batang Top 10 Grup Pengguna
     */
    const topUserGroupsChartCtx = document.getElementById('wpdevTopUserGroupsChart');
    if (topUserGroupsChartCtx && topUserGroupsData && topUserGroupsData.labels.length > 0) {
        new Chart(topUserGroupsChartCtx, {
            type: 'bar',
            data: {
                labels: topUserGroupsData.labels, // Menggunakan label yang dipotong untuk sumbu
                datasets: [{
                    label: 'Jumlah Salinan',
                    data: topUserGroupsData.values,
                    backgroundColor: '#36A2EB',
                    borderColor: '#2793D9',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Top 10 Grup Pengguna' },
                    // BARU: Terapkan helper tooltip untuk menampilkan nama grup lengkap saat hover
                    tooltip: createTooltipConfig(topUserGroupsData)
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    /**
     * Inisialisasi Grafik Batang untuk Distribusi Perangkat
     */
    const deviceChartCtx = document.getElementById('wpdevDeviceChart');
    if (deviceChartCtx && deviceData && deviceData.labels.length > 0) {
        new Chart(deviceChartCtx, {
            type: 'bar',
            data: {
                labels: deviceData.labels,
                datasets: [{
                    label: 'Total Penggunaan',
                    data: deviceData.values,
                    backgroundColor: ['#4A90E2', '#F5A623', '#50E3C2'],
                    borderColor: ['#3A80D2', '#E59613', '#40D3B2'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Total Penggunaan Perangkat'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
});