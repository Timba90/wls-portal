/**
 * Automatischer Logout nach Inaktivität.
 *
 * Die Sitzung läuft serverseitig über SESSION_LIFETIME ab. Dieser Timer sorgt
 * dafür, dass der Browser danach nicht auf einem veralteten Bildschirm stehen
 * bleibt, sondern zur Anmeldung zurückkehrt.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('idleLogout', ({ minutes = 30 } = {}) => ({
        timeout: null,

        init() {
            const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];

            events.forEach((event) => {
                window.addEventListener(event, () => this.reset(), { passive: true });
            });

            document.addEventListener('livewire:navigated', () => this.reset());

            this.reset();
        },

        reset() {
            window.clearTimeout(this.timeout);

            this.timeout = window.setTimeout(() => {
                window.location.assign('/login?abgelaufen=1');
            }, minutes * 60 * 1000);
        },
    }));
});
