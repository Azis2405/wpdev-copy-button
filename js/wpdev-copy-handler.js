/**
 * WPDev Copy Button Handler
 * Versi 1.9.0 - Terintegrasi dengan Halaman Pengaturan
 */
document.addEventListener('DOMContentLoaded', () => {
    // Gunakan pengaturan dari PHP jika ada, jika tidak gunakan nilai default.
    // Objek 'wpdev_copy_settings' dikirim dari file utama plugin.
    const settings = typeof wpdev_copy_settings !== 'undefined' 
        ? wpdev_copy_settings 
        : { ajax_url: '', nonce: '', duration: 2000, disable_on_copy: true };

    const copyButtons = document.querySelectorAll('.wpdev-copy-btn');

    if (copyButtons.length === 0) {
        return;
    }

    copyButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const targetId = button.dataset.targetId;
            const originalText = button.dataset.originalText || 'Salin';
            const buttonTextElement = button.querySelector('.wpdev-btn-text');

            if (!targetId) {
                console.error('WPDev Copy Button: Atribut data-target-id tidak ditemukan.');
                return;
            }

            const targetElement = document.getElementById(targetId);

            if (!targetElement) {
                console.error(`WPDev Copy Button: Elemen dengan ID "${targetId}" tidak ditemukan.`);
                return;
            }

            const textToCopy = targetElement.innerText || targetElement.textContent;

            navigator.clipboard.writeText(textToCopy).then(() => {
                if (buttonTextElement) {
                    buttonTextElement.innerText = 'Tersalin!';
                }
                
                // **INI BAGIAN PENTINGNYA**
                // Periksa pengaturan 'disable_on_copy' sebelum menonaktifkan tombol.
                if (settings.disable_on_copy) {
                    button.disabled = true;
                }

                trackCopyAction(targetId);

                // Gunakan durasi dari pengaturan untuk mengembalikan tombol ke normal.
                setTimeout(() => {
                    if (buttonTextElement) {
                        buttonTextElement.innerText = originalText;
                    }
                    // Aktifkan kembali tombol jika sebelumnya dinonaktifkan.
                    if (settings.disable_on_copy) {
                        button.disabled = false;
                    }
                }, settings.duration);

            }).catch(err => {
                console.error('WPDev Copy Button: Gagal menyalin teks.', err);
            });
        });
    });

    /**
     * Fungsi untuk mengirim data pelacakan via AJAX.
     * @param {string} targetId ID dari elemen yang disalin.
     */
    function trackCopyAction(targetId) {
        if (!settings.ajax_url) return;

        const formData = new FormData();
        formData.append('action', 'wpdev_track_copy');
        formData.append('nonce', settings.nonce);
        formData.append('target_id', targetId);
        formData.append('page_url', window.location.href);

        fetch(settings.ajax_url, {
            method: 'POST',
            body: formData
        }).catch(error => console.error('WPDev Tracker Error:', error));
    }
});