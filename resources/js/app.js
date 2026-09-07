import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('cnicReview', (config) => ({
        cnic: config.cnic || '',
        checking: false,
        confirmOpen: false,
        confirmed: false,
        duplicateMessage: '',
        checkUrl: config.checkUrl,
        csrf: config.csrf,
        async submit() {
            this.duplicateMessage = '';
            this.checking = true;

            try {
                const body = new URLSearchParams();
                body.set('cnic', this.cnic || document.getElementById('cnic').value);
                body.set('_token', this.csrf);

                const response = await fetch(this.checkUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body,
                });
                const payload = await response.json();

                if (payload.duplicate) {
                    const link = payload.url
                        ? ` <a class="font-medium underline" href="${payload.url}">Open existing record</a>`
                        : '';
                    this.duplicateMessage = `A record with this CNIC already exists.${link}`;
                    return;
                }

                this.confirmOpen = true;
            } catch (error) {
                this.confirmOpen = true;
            } finally {
                this.checking = false;
            }
        },
        confirmAndSubmit() {
            this.confirmed = true;
            this.$nextTick(() => {
                document.getElementById('review-form').submit();
            });
        },
    }));
});

Alpine.start();
