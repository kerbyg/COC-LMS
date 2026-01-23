/**
 * CIT-LMS Student Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    animateStats();
    initProgressRings();
});

function animateStats() {
    document.querySelectorAll('.stat-number').forEach(stat => {
        const text = stat.textContent;
        const hasPercent = text.includes('%');
        const value = parseInt(text) || 0;
        let current = 0;
        
        if (value > 0) {
            const increment = value / 60;
            const animate = () => {
                current += increment;
                if (current < value) {
                    stat.textContent = Math.floor(current) + (hasPercent ? '%' : '');
                    requestAnimationFrame(animate);
                } else {
                    stat.textContent = value + (hasPercent ? '%' : '');
                }
            };
            stat.textContent = '0' + (hasPercent ? '%' : '');
            animate();
        }
    });
}

function initProgressRings() {
    document.querySelectorAll('.prog-ring').forEach(ring => {
        const prog = ring.style.getPropertyValue('--prog');
        ring.style.setProperty('--prog', '0%');
        setTimeout(() => {
            ring.style.transition = 'all 0.6s ease';
            ring.style.setProperty('--prog', prog);
        }, 200);
    });
}