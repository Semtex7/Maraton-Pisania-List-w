jQuery(document).ready(function ($) {
    try {
        console.log("Events App Initialized");

        // Fix Leaflet icons (fetch from reliable CDN)
        if (typeof L !== 'undefined') {
            let defaultIcon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconAnchor: [12, 41],
                popupAnchor: [1, -34]
            });
            L.Marker.prototype.options.icon = defaultIcon;
        }

        /* ------------------------------------------------------------
         * Global Namespace
         * ------------------------------------------------------------ */
        window.KK = {
            init: function () {
                this.initMap();
                this.fileUploader();
                this.initPanelAjax();
                this.stickyHeader();
                this.blockCounter();
                this.eventModal()
            },

            initMap: function () {
                if (typeof L === 'undefined') return; 
                let mapContainer = document.getElementById('events-map-canvas');
                if (!mapContainer) return;

                let map = L.map('events-map-canvas').setView([51.9194, 19.1451], 6);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Asynchroniczne pobieranie danych wydarzeń przez AJAX
                fetch(kkAjax.ajaxurl + '?action=get_events_map_data')
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.data.length > 0) {
                            let eventsData = res.data;
                            let allMarkers = [];
                            let initialBounds = L.latLngBounds();

                            eventsData.forEach(function (eventItem) {
                                let markerClass = eventItem.is_public ? 'amnesty-pin-public' : 'amnesty-pin-private';

                                let customIcon = L.icon({
                                    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                                    iconAnchor: [12, 41],
                                    popupAnchor: [1, -34],
                                    className: markerClass
                                });

                                let marker = L.marker([eventItem.lat, eventItem.lng], { icon: customIcon });
                                
                                let popupText = '<strong>' + eventItem.title + '</strong><br>' + eventItem.address;
                                if (!eventItem.is_public) {
                                    popupText += '<br><br><span style="color: #d32f2f; font-weight: bold; padding: 2px 0;">Wydarzenie zamknięte</span><br><span style="font-size: 11px;">(niedostępne dla osób z zewnątrz)</span>';
                                }

                                marker.bindPopup(popupText);
                                marker.addTo(map);

                                let position = marker.getLatLng();
                                initialBounds.extend(position);

                                allMarkers.push({
                                    markerObject: marker,
                                    title: eventItem.title.toLowerCase(),
                                    address: eventItem.address.toLowerCase(),
                                    position: position
                                });
                            });

                            if (allMarkers.length > 0) {
                                map.fitBounds(initialBounds, { padding: [40, 40], maxZoom: 14 });
                            }

                            // Filtrowanie mapy
                            let searchBar = document.getElementById('map-search-bar');
                            if (searchBar) {
                                searchBar.addEventListener('input', function (e) {
                                    let searchQuery = e.target.value.toLowerCase().trim();
                                    let visibleBounds = L.latLngBounds();
                                    let visibleCount = 0;

                                    allMarkers.forEach(function (item) {
                                        let matchesTitle = item.title.includes(searchQuery);
                                        let matchesAddress = item.address.includes(searchQuery);

                                        if (matchesTitle || matchesAddress) {
                                            if (!map.hasLayer(item.markerObject)) {
                                                map.addLayer(item.markerObject);
                                            }
                                            visibleBounds.extend(item.position);
                                            visibleCount++;
                                        } else {
                                            if (map.hasLayer(item.markerObject)) {
                                                map.removeLayer(item.markerObject);
                                            }
                                        }
                                    });

                                    if (visibleCount > 0) {
                                        map.flyToBounds(visibleBounds, {
                                            padding: [50, 50],
                                            maxZoom: 12,
                                            duration: 0.6
                                        });
                                    }
                                });
                            }
                        }
                    })
                    .catch(err => console.error('Błąd pobierania danych mapy:', err));
            },
            fileUploader: function () {
                if (typeof wp !== 'undefined' && wp.media) {
                    let mediaFrame;
                    let activeInputField; // This dynamically tracks which input should receive the URL

                    // Handle Open Media Library Buttons
                    const uploadButtons = document.querySelectorAll('.upload-media-btn');
                    uploadButtons.forEach(function (button) {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();

                            // Set the active input field every time a button is clicked
                            const targetId = this.getAttribute('data-target');
                            activeInputField = document.getElementById(targetId);

                            // If the frame already exists, just open it and stop
                            if (mediaFrame) {
                                mediaFrame.open();
                                return;
                            }

                            // Initialize the WordPress media uploader
                            mediaFrame = wp.media({
                                title: 'Wybierz lub wgraj plik',
                                button: { text: 'Użyj tego pliku' },
                                multiple: false
                            });

                            // When a file is selected, assign URL to the currently active input
                            mediaFrame.on('select', function () {
                                const attachment = mediaFrame.state().get('selection').first().toJSON();
                                if (activeInputField) {
                                    activeInputField.value = attachment.url;
                                }
                            });

                            mediaFrame.open();
                        });
                    });

                    // Handle Clear Buttons
                    const clearButtons = document.querySelectorAll('.clear-media-btn');
                    clearButtons.forEach(function (button) {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();
                            const targetId = this.getAttribute('data-target');
                            document.getElementById(targetId).value = '';
                        });
                    });
                }
            },


            // ============================================================
            // NEW MODULE: User Panel AJAX Logic (Edit & Reports)
            // ============================================================
            initPanelAjax: function () {
                const eventsTable = document.getElementById('ajax-table-container');
                const formContainer = document.getElementById('ajax-form-container');
                const mainMessages = document.getElementById('ajax-main-messages');

                // Safety check: Exit if we are not on the Panel page or maratonConfig is missing
                if (!eventsTable || typeof kkAjax === 'undefined' || !kkAjax.ajaxurl) return;

                const ajaxUrl = kkAjax.ajaxurl;

                eventsTable.addEventListener('click', function (e) {
                    // Handle Edit Click
                    if (e.target.classList.contains('open-ajax-edit')) {
                        e.preventDefault();
                        let postId = e.target.getAttribute('data-id');
                        loadAjaxForm('get_event_form', postId, attachFormSubmitHandler);
                    }

                    // Handle Report Click
                    if (e.target.classList.contains('open-ajax-report')) {
                        e.preventDefault();
                        let postId = e.target.getAttribute('data-id');
                        loadAjaxForm('get_report_form', postId, attachReportSubmitHandler);
                    }
                });

                function loadAjaxForm(actionName, postId, callbackSuccess) {
                    eventsTable.style.display = 'none';
                    formContainer.style.display = 'block';
                    formContainer.innerHTML = '<p style="font-weight: bold; color: #555;">Pobieranie formularza...</p>';

                    let formRequest = new FormData();
                    formRequest.append('action', actionName);
                    formRequest.append('post_id', postId);

                    fetch(ajaxUrl, { method: 'POST', body: formRequest })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                formContainer.innerHTML = res.data;
                                callbackSuccess();
                            } else {
                                formContainer.innerHTML = '<p style="color:red;">Błąd: ' + res.data + '</p>';
                            }
                        });
                }

                function attachFormSubmitHandler() {
                    document.getElementById('cancel-ajax-edit').addEventListener('click', closeFormContainer);
                    document.getElementById('ajax-edit-form').addEventListener('submit', function (e) {
                        e.preventDefault();
                        submitFormGeneric(this, 'ajax-save-button', 'edit-status-message', 'Zapisano zmiany!');
                    });
                }

                function attachReportSubmitHandler() {
                    document.getElementById('cancel-ajax-report').addEventListener('click', closeFormContainer);
                    document.getElementById('ajax-report-form').addEventListener('submit', function (e) {
                        e.preventDefault();
                        submitFormGeneric(this, 'ajax-report-submit-button', 'report-status-message', 'Raport został wysłany!');
                    });
                }

                function closeFormContainer(e) {
                    if (e) e.preventDefault();
                    formContainer.style.display = 'none';
                    formContainer.innerHTML = '';
                    eventsTable.style.display = 'block';
                }

                function submitFormGeneric(formElement, buttonId, messageId, successText) {
                    let saveBtn = document.getElementById(buttonId);
                    let statusMsg = document.getElementById(messageId);

                    saveBtn.disabled = true;
                    let originalText = saveBtn.innerText;
                    saveBtn.innerText = 'Trwa przesyłanie...';

                    let formData = new FormData(formElement);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                statusMsg.style.color = 'green';
                                statusMsg.innerText = res.data;
                                setTimeout(() => {
                                    closeFormContainer();
                                    mainMessages.innerHTML = '<div style="color: #155724; background-color: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px;">' + successText + '</div>';
                                    setTimeout(() => { location.reload(); }, 1000); // Reload to lock the report button
                                }, 1500);
                            } else {
                                statusMsg.style.color = 'red';
                                statusMsg.innerText = res.data;
                                saveBtn.disabled = false;
                                saveBtn.innerText = originalText;
                            }
                        }).catch(err => {
                            statusMsg.style.color = 'red';
                            statusMsg.innerText = 'Błąd sieci lub za duży plik ZIP.';
                            saveBtn.disabled = false;
                            saveBtn.innerText = originalText;
                        });
                }
            },

            stickyHeader: function() {
                const stickyHeader = document.querySelector('.header-sticky');
    
                if (!stickyHeader) return;

                const placeholder = document.createElement('div');
                placeholder.style.display = 'none';
                
                stickyHeader.parentNode.insertBefore(placeholder, stickyHeader);
                const headerOffset = stickyHeader.getBoundingClientRect().top + window.scrollY;

                window.addEventListener('scroll', () => {
                    
                    if (window.scrollY >= headerOffset) {
                        
                        stickyHeader.classList.add('is-fixed');
                        
                        placeholder.style.height = `${stickyHeader.offsetHeight}px`;
                        placeholder.style.display = 'block';
                        
                    } else {
                        
                        stickyHeader.classList.remove('is-fixed');
                        placeholder.style.display = 'none';
                        
                    }
                });
            },

            blockCounter: function() {
                const counters = document.querySelectorAll('.custom-block-counter-wrapper');
                
                if (!counters.length) return;

                // Uruchamiamy animację dopiero, gdy użytkownik dojedzie do licznika (widoczność w 50%)
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            animateCounter(entry.target);
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });

                counters.forEach(counter => {
                    observer.observe(counter);
                });

                // Główna logika licząca
                function animateCounter(wrapper) {
                    const targetValue = parseInt(wrapper.getAttribute('data-target'), 10);
                    const duration = parseInt(wrapper.getAttribute('data-duration'), 10);
                    const targetLength = targetValue.toString().length;
                    const spans = wrapper.querySelectorAll('.counter-digit');
                    
                    let startTime = null;

                    function update(currentTime) {
                        if (!startTime) startTime = currentTime;
                        const elapsed = currentTime - startTime;
                        
                        const progress = Math.min(elapsed / duration, 1);
                        const easeOutProgress = 1 - Math.pow(1 - progress, 3);
                        
                        const currentNumber = Math.floor(targetValue * easeOutProgress);
                        const paddedNumber = currentNumber.toString().padStart(targetLength, '0');
                        
                        spans.forEach((span, index) => {
                            span.textContent = paddedNumber[index];
                        });

                        if (progress < 1) {
                            requestAnimationFrame(update);
                        } else {
                            // Zabezpieczenie dokładnej wartości docelowej po zakończeniu klatki
                            const finalPadded = targetValue.toString().padStart(targetLength, '0');
                            spans.forEach((span, index) => {
                                span.textContent = finalPadded[index];
                            });
                        }
                    }

                    requestAnimationFrame(update);
                }
            },

            eventModal: function() {
                const modal = document.getElementById('amnesty-event-modal');
                const openBtn = document.getElementById('open-new-event-form'); // Id z poprzedniego shortcode'u
                const closeBtns = document.querySelectorAll('.amnesty-modal-close, .amnesty-modal-overlay');

                if (!modal) return;

                // Open Modal
                if (openBtn) {
                    openBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        modal.classList.add('is-open');
                        document.body.style.overflow = 'hidden'; // Zapobiega scrollowaniu strony pod modalem
                    });
                }

                // Close Modal
                closeBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        modal.classList.remove('is-open');
                        document.body.style.overflow = ''; 
                    });
                });

                // Sprawdzenie, czy modal ma klasę 'is-open' przy starcie (wymuszone przez PHP w razie błędów)
                if (modal.classList.contains('is-open')) {
                    document.body.style.overflow = 'hidden';
                }
            }
        };

        /* ------------------------------------------------------------
         * Initialize All Modules
         * ------------------------------------------------------------ */
        KK.init();
    } catch (err) {
        console.error("Events App error:", err);
    }
});