class PageLoader {
    constructor() {
        this.loader = document.getElementById('pageLoader');
        this.progress = document.getElementById('loadingProgress');
        this.currentProgress = 0;
        this.isLoading = false;
    }

    init() {
        this.show();
        this.simulateLoading();
    }

    show() {
        if (this.loader) {
            this.isLoading = true;
            this.loader.style.display = 'flex';
            this.loader.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    hide() {
        if (this.loader && this.isLoading) {
            this.loader.classList.add('hidden');
            setTimeout(() => {
                this.loader.style.display = 'none';
                document.body.style.overflow = '';
                this.isLoading = false;
                this.currentProgress = 0;
                if (this.progress) {
                    this.progress.style.width = '0%';
                }
            }, 500);
        }
    }

    updateProgress(value) {
        if (this.progress && this.isLoading) {
            this.currentProgress = Math.min(value, 100);
            this.progress.style.width = `${this.currentProgress}%`;
        }
    }

    simulateLoading() {
        const steps = [
            { progress: 20, delay: 1000 },
            { progress: 40, delay: 2000 },
            { progress: 60, delay: 3000 },
            { progress: 80, delay: 4000 },
            { progress: 90, delay: 5000 }
        ];

        steps.forEach(step => {
            setTimeout(() => {
                if (this.isLoading) {
                    this.updateProgress(step.progress);
                }
            }, step.delay);
        });
    }

    completeLoading() {
        if (this.isLoading) {
            const remainingProgress = 100 - this.currentProgress;
            const steps = 10;
            const increment = remainingProgress / steps;
            const stepDuration = 100;

            for (let i = 1; i <= steps; i++) {
                setTimeout(() => {
                    if (this.isLoading) {
                        this.updateProgress(this.currentProgress + (increment * i));
                    }
                }, stepDuration * i);
            }

            setTimeout(() => {
                this.hide();
            }, stepDuration * (steps + 5));
        }
    }
}

// Create global instance
window.pageLoader = new PageLoader();

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.pageLoader.init();
});

// Show loader on page transitions
window.addEventListener('beforeunload', () => {
    window.pageLoader.show();
});

// Handle form submissions
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            window.pageLoader.show();
            window.pageLoader.simulateLoading();
        });
    });
});
