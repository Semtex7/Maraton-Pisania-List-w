document.addEventListener('DOMContentLoaded', function() {
    
    const initBlockCounter = () => {
        const counters = document.querySelectorAll('.custom-block-counter-wrapper');
        
        // Stop execution if there are no counters on the current page
        if (!counters.length) return;

        // Create an observer to start animation only when the counter is visible on screen
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    // Stop observing once the animation has started
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        // Attach the observer to all found counters
        counters.forEach(counter => {
            observer.observe(counter);
        });

        // Core animation function
        function animateCounter(wrapper) {
            const targetValue = parseInt(wrapper.getAttribute('data-target'), 10);
            const duration = parseInt(wrapper.getAttribute('data-duration'), 10);
            const targetLength = targetValue.toString().length;
            const spans = wrapper.querySelectorAll('.counter-digit');
            
            let startTime = null;

            function update(currentTime) {
                if (!startTime) startTime = currentTime;
                const elapsed = currentTime - startTime;
                
                // Calculate progress between 0 and 1
                const progress = Math.min(elapsed / duration, 1);
                
                // Apply ease-out mathematical effect for smooth deceleration
                const easeOutProgress = 1 - Math.pow(1 - progress, 3);
                
                const currentNumber = Math.floor(targetValue * easeOutProgress);
                // Pad the current number with leading zeros to match the target length
                const paddedNumber = currentNumber.toString().padStart(targetLength, '0');
                
                // Update each span with the corresponding digit
                spans.forEach((span, index) => {
                    span.textContent = paddedNumber[index];
                });

                // Continue animation if not finished
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    // Ensure the exact target value is displayed at the very end
                    const finalPadded = targetValue.toString().padStart(targetLength, '0');
                    spans.forEach((span, index) => {
                        span.textContent = finalPadded[index];
                    });
                }
            }

            requestAnimationFrame(update);
        }
    };

    // Run the initialization function
    initBlockCounter();

});