console.log("TAV: JS Loaded");
jQuery(document).ready(function ($) {
    const $sidebar = $('.tav-sidebar');
    const $collapseBtn = $('#tav-sidebar-collapse');
    const storageKey = 'tav_sidebar_collapsed';

    // Collapsed by default — only expand if the user explicitly opened it
    if (localStorage.getItem(storageKey) !== 'false') {
        $sidebar.addClass('collapsed');
    }

    $collapseBtn.on('click', function () {
        $sidebar.toggleClass('collapsed');
        localStorage.setItem(storageKey, $sidebar.hasClass('collapsed'));
    });

    // Fulfillment Save & Notify Loading State
    const showLoading = function () {
        console.log('TAV: showLoading triggered');
        const $btn = $('#tav-save-fulfillment');
        if ($btn.length) {
            console.log('TAV: Button found, showing spinner');
            $btn.data('loading', true);
            $btn.find('.tav-btn-text').css('opacity', '0.5');
            $btn.find('.tav-spinner').show();
        } else {
            console.log('TAV: Button #tav-save-fulfillment NOT found');
        }
    };

    $(document).on('click', '#tav-save-fulfillment', function () {
        console.log('TAV: Button clicked');
        showLoading();
    });

    $(document).on('submit', 'form', function () {
        console.log('TAV: Form submitted');
        showLoading();
    });

    // Storyteller Modal Logic
    const $modal = $('#tav-storyteller-modal');
    const $modalContent = $('#tav-modal-content');

    $(document).on('click', '.tav-view-storyteller', function (e) {
        e.preventDefault();
        const stId = $(this).data('st-id');
        $modal.fadeIn(200);
        $modalContent.html('<div class="tav-modal-loader"><span class="tav-spinner"></span><p>Loading details...</p></div>');

        $.ajax({
            url: tavData.ajaxurl,
            type: 'GET',
            data: {
                action: 'tav_get_storyteller_details',
                st_id: stId,
                nonce: tavData.nonce
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    let html = `
                        <div class="tav-st-details">
                            <div class="tav-st-profile-header">
                                ${data.thumbnail ? `<img src="${data.thumbnail}" class="tav-st-modal-thumb">` : `<div class="tav-st-modal-placeholder">${data.title.charAt(0)}</div>`}
                                <div class="tav-st-modal-info">
                                    <h3>${data.title}</h3>
                                    <p class="tav-st-modal-location"><span class="dashicons dashicons-location"></span> ${data.location}</p>
                                </div>
                            </div>
                            <div class="tav-st-modal-section">
                                <h4>Bio</h4>
                                <div class="tav-st-modal-bio">${data.bio}</div>
                            </div>
                    `;

                    if (data.platforms && data.platforms.length > 0) {
                        html += `
                            <div class="tav-st-modal-section">
                                <h4>Platforms</h4>
                                <div class="tav-st-platforms-grid">
                                    ${data.platforms.map(p => `
                                        <div class="tav-st-platform-item">
                                            <strong>${p.name.charAt(0).toUpperCase() + p.name.slice(1)}</strong>
                                            <span>${p.handle}</span>
                                            <small>${p.followers ? parseInt(p.followers).toLocaleString() : 0} followers</small>
                                            <a href="${p.url}" target="_blank" class="tav-st-modal-link">View Profile</a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    if (data.samples && data.samples.length > 0) {
                        html += `
                            <div class="tav-st-modal-section">
                                <h4>Sample Work</h4>
                                <div class="tav-st-samples-list">
                                    ${data.samples.map(s => `
                                        <div class="tav-st-sample-item">
                                            <div class="tav-st-sample-info">
                                                <strong>${s.title}</strong>
                                                <span>${s.platform.toUpperCase()} • ${s.views ? parseInt(s.views).toLocaleString() : 0} views</span>
                                            </div>
                                            <a href="${s.url}" target="_blank" class="dashicons dashicons-external"></a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    html += `</div>`;
                    $modalContent.html(html);
                } else {
                    $modalContent.html(`<div class="tav-modal-error">${response.data.message || 'Error loading details.'}</div>`);
                }
            },
            error: function () {
                $modalContent.html('<div class="tav-modal-error">Failed to connect to server.</div>');
            }
        });
    });

    $('.tav-modal-close, .tav-modal-overlay').on('click', function () {
        $modal.fadeOut(200);
    });
});
