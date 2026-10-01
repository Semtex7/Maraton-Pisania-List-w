document.addEventListener('DOMContentLoaded', function() {

    // 1. LETTER TYPE TOGGLE LOGIC (Obsługa zakładek listu)
    const toggleBtns = document.querySelectorAll('.amnesty-toggle-btn');
    const letterTypeInput = document.querySelector('input[name="letter_type"]');
    const customMessageTextarea = document.querySelector('textarea[name="custom_message"]');

    const updateLetterTypeUI = (btn) => {
        if (!btn) return;
        
        // Find the container for this specific form instance
        const container = btn.closest('.amnesty-petition-form') || document;
        const localBtns = container.querySelectorAll('.amnesty-toggle-btn');
        
        // Reset active states
        localBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Update the hidden input for backend processing
        const selectedType = btn.getAttribute('data-type');
        const localInput = container.querySelector('input[name="letter_type"]');
        if (localInput) {
            localInput.value = selectedType;
        }
        
        // Update the textarea with dynamic content from the database
        const localTextarea = container.querySelector('textarea[name="custom_message"]');
        const dynamicMessage = btn.getAttribute('data-message');
        
        // Only update if the textarea exists and the button has a message attached
        if (localTextarea && dynamicMessage !== null) {
            localTextarea.value = dynamicMessage;
        }
    };

    if (toggleBtns.length > 0) {
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); 
                updateLetterTypeUI(this);
            });
        });

        // Ustaw domyślnie pierwszy przycisk (List do władz) jako aktywny na starcie
        // Znajdujemy pierwszy przycisk w każdym formularzu i aktywujemy go
        document.querySelectorAll('.amnesty-petition-form').forEach(form => {
            const firstBtn = form.querySelector('.amnesty-toggle-btn');
            if (firstBtn) {
                updateLetterTypeUI(firstBtn);
            }
        });
    }

    // 2. AJAX FORM SUBMISSION (Żelazna blokada podwójnego strzału)
    const forms = document.querySelectorAll('.amnesty-petition-form');

    forms.forEach(form => {
        // Zapobiegamy wielokrotnemu podpięciu tego samego listenera do tego samego formularza
        if (form.dataset.listenerAttached === 'true') return;
        form.dataset.listenerAttached = 'true';

        form.addEventListener('submit', function(e) {
            e.preventDefault(); 

            const submitBtn = form.querySelector('.amnesty-petition-submit-btn');
            
            // Jeśli przycisk jest już zablokowany, natychmiast przerywamy (eliminacja dubli)
            if (submitBtn && submitBtn.disabled) {
                return;
            }

            const originalBtnText = submitBtn ? submitBtn.textContent : '';
            
            if (submitBtn) {
                submitBtn.textContent = 'Wysyłanie...';
                submitBtn.disabled = true; // Fizyczna blokada przycisku
            }

            const formData = new FormData(form);
            formData.append('action', 'submit_amnesty_petition');
            formData.append('amnesty_sign_nonce', amnestyAjax.nonce);

            fetch(amnestyAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Dziękujemy! Twój głos został zapisany.');
                    form.reset(); 
                    
                    const firstBtn = form.querySelector('.amnesty-toggle-btn');
                    if (firstBtn) updateLetterTypeUI(firstBtn);
                } else {
                    alert('Błąd: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas komunikacji z serwerem.');
            })
            .finally(() => {
                // Przywracamy przycisk dopiero po pełnym zakończeniu requestu
                if (submitBtn) {
                    submitBtn.textContent = originalBtnText;
                    submitBtn.disabled = false;
                }
            });
        });
    });

    // 3. VIDEO MODAL LOGIC
    const videoBtns = document.querySelectorAll('.amnesty-btn-video');
    
    if (videoBtns.length > 0 && !document.querySelector('.amnesty-video-modal')) {
        const overlay = document.createElement('div');
        overlay.className = 'amnesty-video-modal';
        overlay.innerHTML = `
            <div class="amnesty-video-content">
                <button class="amnesty-video-close">&times;</button>
                <div class="amnesty-video-iframe-wrapper"></div>
            </div>
        `;
        
        document.body.appendChild(overlay);

        const videoContainer = overlay.querySelector('.amnesty-video-iframe-wrapper');
        const closeBtn = overlay.querySelector('.amnesty-video-close');

        videoBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                let videoUrl = this.getAttribute('data-video-url');
                
                if (!videoUrl) return;

                if (videoUrl.includes('watch?v=')) {
                    videoUrl = videoUrl.replace('watch?v=', 'embed/');
                }
                
                videoContainer.innerHTML = `<iframe src="${videoUrl}?autoplay=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                overlay.classList.add('is-active');
            });
        });

        const closeModal = () => {
            overlay.classList.remove('is-active');
            videoContainer.innerHTML = ''; 
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-active')) closeModal();
        });
    }
});