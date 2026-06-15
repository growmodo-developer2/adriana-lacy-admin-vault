jQuery(document).ready(function ($) {
    const $sidebar = $('#tav-sidebar');
    const $collapseBtn = $('#tav-sidebar-collapse');
    const $wrap = $('.tav-dashboard-wrap');
    const $mobileMenuBtn = $('#tav-mobile-menu-btn');
    const $sidebarOverlay = $('#tav-sidebar-overlay');
    const storageKey = 'tav_sidebar_collapsed';
    const mobileBreakpoint = 960;

    function tavIsMobileNav() {
        return window.matchMedia('(max-width: ' + mobileBreakpoint + 'px)').matches;
    }

    function tavApplySidebarState(collapsed) {
        if (!$sidebar.length) {
            return;
        }

        $sidebar.toggleClass('collapsed', collapsed);
        $wrap.toggleClass('is-sidebar-collapsed', collapsed);

        if ($collapseBtn.length) {
            $collapseBtn.attr('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function tavSetMobileNavOpen(open) {
        $wrap.toggleClass('is-mobile-nav-open', open);

        if ($mobileMenuBtn.length) {
            $mobileMenuBtn.attr('aria-expanded', open ? 'true' : 'false');
            $mobileMenuBtn.attr(
                'aria-label',
                open ? 'Close menu' : 'Open menu'
            );
        }

        if ($sidebarOverlay.length) {
            $sidebarOverlay.attr('aria-hidden', open ? 'false' : 'true');
            $sidebarOverlay.attr('tabindex', open ? '0' : '-1');
        }

        $('body').toggleClass('tav-mobile-nav-open', open);
    }

    function tavCloseMobileNav() {
        tavSetMobileNavOpen(false);
    }

    if ($sidebar.length && $collapseBtn.length) {
        const stored = localStorage.getItem(storageKey);
        tavApplySidebarState(stored === 'true');

        $collapseBtn.on('click', function (event) {
            event.preventDefault();
            const collapsed = !$sidebar.hasClass('collapsed');
            tavApplySidebarState(collapsed);
            localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
        });
    }

    if ($mobileMenuBtn.length) {
        $mobileMenuBtn.on('click', function (event) {
            event.preventDefault();
            tavSetMobileNavOpen(!$wrap.hasClass('is-mobile-nav-open'));
        });
    }

    if ($sidebarOverlay.length) {
        $sidebarOverlay.on('click', function () {
            tavCloseMobileNav();
        });

        $sidebarOverlay.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
                e.preventDefault();
                tavCloseMobileNav();
            }
        });
    }

    $sidebar.on('click', '.tav-sidebar-nav a, .tav-sidebar-user-card', function () {
        if (tavIsMobileNav()) {
            tavCloseMobileNav();
        }
    });

    $(window).on('resize', function () {
        if (!tavIsMobileNav()) {
            tavCloseMobileNav();
        }
    });
    
    // ── Requests Page: Filters Toggle ──────────────────────────────
    const $filtersToggle = $('#tav-toggle-filters');
    const $advancedFilters = $('#tav-advanced-filters');
    const filtersStorageKey = 'tav_filters_visible';
    
    if ($filtersToggle.length && $advancedFilters.length) {
        // Restore filter visibility state
        if (localStorage.getItem(filtersStorageKey) === 'true') {
            $advancedFilters.show();
            $filtersToggle.addClass('active');
        }
        
        $filtersToggle.on('click', function () {
            $advancedFilters.slideToggle(200);
            $(this).toggleClass('active');
            localStorage.setItem(filtersStorageKey, $advancedFilters.is(':visible'));
        });
    }

    // ── Revenue chart ───────────────────────────────────────────────
    function tavReadInitialChartData() {
        if (window.tavData && window.tavData.chart) {
            return window.tavData.chart;
        }
        var node = document.getElementById('tav-chart-initial-data');
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function tavFormatMoney(value) {
        return '$' + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    }

    function tavInitRevenueChart() {
        var canvas = document.getElementById('tav-revenue-chart');
        if (!canvas || typeof Chart === 'undefined') {
            return null;
        }

        var initial = tavReadInitialChartData() || {
            labels: [],
            received: [],
            pending: [],
            total: 0,
        };

        var chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: initial.labels || [],
                datasets: [
                    {
                        label: 'Received',
                        data: initial.received || [],
                        borderColor: '#269EB2',
                        backgroundColor: 'rgba(38, 158, 178, 0.14)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'Pending',
                        data: initial.pending || [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + tavFormatMoney(ctx.parsed.y);
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#94a3b8',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.18)' },
                        ticks: {
                            color: '#94a3b8',
                            callback: function (v) {
                                return tavFormatMoney(v);
                            },
                        },
                    },
                },
            },
        });

        function tavUpdateChartTotal(total) {
            var $total = $('#tav-chart-total');
            if ($total.length) {
                $total.text(tavFormatMoney(total));
            }
        }

        tavUpdateChartTotal(initial.total || 0);

        $(document).on('click', '.tav-chart-btn', function () {
            var $btn = $(this);
            var period = $btn.data('period');
            if (!period || !window.tavData) {
                return;
            }

            $('.tav-chart-btn').removeClass('tav-chart-active');
            $btn.addClass('tav-chart-active');

            $.post(window.tavData.ajaxurl, {
                action: 'tav_get_chart_data',
                nonce: window.tavData.nonce,
                period: period,
            }).done(function (res) {
                if (!res || !res.success || !res.data) {
                    return;
                }
                chart.data.labels = res.data.labels || [];
                chart.data.datasets[0].data = res.data.received || [];
                chart.data.datasets[1].data = res.data.pending || [];
                chart.update();
                tavUpdateChartTotal(res.data.total || 0);
            });
        });

        return chart;
    }

    tavInitRevenueChart();

    // Fulfillment — Assign to Project loading state
    const showLoading = function () {
        const $btn = $('#tav-save-fulfillment');
        if ($btn.length) {
            $btn.data('loading', true);
            $btn.find('.tav-btn-text').css('opacity', '0.5');
            $btn.find('.tav-spinner').show();
        }
    };

    $(document).on('click', '#tav-save-fulfillment', function () {
        showLoading();
    });

    $(document).on('submit', '#tav-fulfillment-form', function () {
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

    // Storyteller modal: close on overlay or × button.
    // Uses a scoped selector so it doesn't interfere with the client modal below.
    $(document).on('click', '#tav-storyteller-modal .tav-modal-close, #tav-storyteller-modal .tav-modal-overlay', function () {
        $modal.fadeOut(200);
    });

    // Legacy direct binding kept as a fallback for any other modals
    // that share .tav-modal-close / .tav-modal-overlay without an explicit handler.
    $('.tav-modal-close, .tav-modal-overlay').not('#tav-client-modal .tav-modal-close, #tav-client-modal .tav-modal-overlay').on('click', function () {
        $modal.fadeOut(200);
    });

    // ── Client Detail Modal ─────────────────────────────────────────
    const $clientModal        = $('#tav-client-modal');
    const $clientModalContent = $('#tav-client-modal-content');

    // Helper: build a 1–2 letter initials string from a display name.
    function tavInitials(name) {
        return (name || '').trim().split(/\s+/)
            .map(function (w) { return w.charAt(0).toUpperCase(); })
            .slice(0, 2).join('');
    }

    // Open modal and fetch details via AJAX.
    $(document).on('click', '.tav-view-client', function () {
        var clientId = $(this).data('client-id');
        $clientModal.fadeIn(200);
        $clientModalContent.html(
            '<div style="text-align:center;padding:40px 0;">' +
            '<span class="tav-spinner" style="display:inline-block;"></span>' +
            '<p style="margin:12px 0 0;color:#64748b;">Loading details…</p></div>'
        );

        $.ajax({
            url:  tavData.ajaxurl,
            type: 'GET',
            data: {
                action:    'tav_get_client_details',
                client_id: clientId,
                nonce:     tavData.nonce,
            },
            success: function (response) {
                if (!response.success) {
                    $clientModalContent.html(
                        '<div class="tav-modal-error" style="color:#dc2626;padding:20px;">' +
                        (response.data && response.data.message ? response.data.message : 'Error loading details.') +
                        '</div>'
                    );
                    return;
                }

                var d = response.data;
                var initials = tavInitials(d.name);

                // Update modal title with company name (falls back to display name).
                var company = d.company || d.name || 'Client';
                document.querySelector('#tav-client-modal .tav-modal-title').textContent =
                    company + ' Account Details';

                // Status <select> options.
                var statusOptions = [
                    { value: 'active',    label: 'Active' },
                    { value: 'suspended', label: 'Suspended' },
                    { value: 'vip',       label: 'VIP' },
                ].map(function (opt) {
                    return '<option value="' + opt.value + '"' +
                           (d.status === opt.value ? ' selected' : '') +
                           '>' + opt.label + '</option>';
                }).join('');

                // Request history rows.
                var reqRows;
                if (d.requests && d.requests.length > 0) {
                    reqRows = d.requests.map(function (r) {
                        return '<tr>' +
                            '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + r.title + '</td>' +
                            '<td><span class="tav-pill" style="--pill-fg:#2271b1;--pill-bg:#e7f3fe;">' + r.package + '</span></td>' +
                            '<td class="tav-cell-secondary">' + r.date + '</td>' +
                            '<td style="font-weight:600;">' + r.total + '</td>' +
                        '</tr>';
                    }).join('');
                } else {
                    reqRows = '<tr><td colspan="4" class="tav-empty" style="text-align:center;padding:16px;color:#94a3b8;">No requests found.</td></tr>';
                }

                var fieldLabel = 'display:block;font-size:12px;font-weight:600;color:#64748b;' +
                                 'margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;';
                var fieldInput = 'width:100%;padding:8px 10px;border:1px solid #d1d5db;' +
                                 'border-radius:6px;font-size:14px;box-sizing:border-box;';

                var html =
                    // ── Profile header ─────────────────────────
                    '<div class="tav-st-profile-header" style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">' +
                        '<div class="tav-boutique-avatar" style="background:#e7f3fe;color:#2271b1;flex-shrink:0;width:52px;height:52px;font-size:18px;">' + initials + '</div>' +
                        '<div>' +
                            '<h3 style="margin:0 0 3px;font-size:17px;font-weight:700;color:#1e293b;">' + d.name + '</h3>' +
                            (d.company ? '<p style="margin:0 0 2px;font-weight:500;color:#334155;">' + d.company + '</p>' : '') +
                            '<p class="tav-cell-secondary" style="margin:0;">' + d.email + '</p>' +
                        '</div>' +
                    '</div>' +

                    // ── Status + Notes ─────────────────────────
                    '<div style="margin-bottom:24px;">' +
                        '<div style="margin-bottom:16px;">' +
                            '<label for="tav-client-status" style="' + fieldLabel + '">Status</label>' +
                            '<select id="tav-client-status" style="' + fieldInput + '">' + statusOptions + '</select>' +
                        '</div>' +
                        '<div>' +
                            '<label for="tav-client-notes" style="' + fieldLabel + '">Internal Notes</label>' +
                            '<textarea id="tav-client-notes" rows="4" style="' + fieldInput + 'resize:vertical;">' + d.notes + '</textarea>' +
                        '</div>' +
                    '</div>' +

                    // ── Request history ────────────────────────
                    '<div style="margin-bottom:24px;">' +
                        '<h4 style="margin:0 0 12px;font-size:14px;font-weight:600;color:#1e293b;">Request History</h4>' +
                        '<div style="overflow-x:auto;">' +
                            '<table class="tav-boutique-table" style="width:100%;min-width:480px;">' +
                                '<thead>' +
                                    '<tr>' +
                                        '<th>Request</th>' +
                                        '<th>Package</th>' +
                                        '<th>Date</th>' +
                                        '<th>Total Paid</th>' +
                                    '</tr>' +
                                '</thead>' +
                                '<tbody>' + reqRows + '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>' +

                    // ── Save footer ────────────────────────────
                    '<div style="display:flex;align-items:center;gap:12px;padding-top:16px;border-top:1px solid #e2e8f0;">' +
                        '<button type="button" id="tav-client-save-btn" ' +
                                'class="tav-btn tav-btn-primary" ' +
                                'data-client-id="' + d.id + '" ' +
                                'style="padding:10px 22px;background:#2271b1;color:#fff;border:none;border-radius:6px;' +
                                       'font-size:14px;font-weight:600;cursor:pointer;">' +
                            'Save Changes' +
                        '</button>' +
                        '<span id="tav-client-save-status" style="font-size:13px;"></span>' +
                    '</div>';

                $clientModalContent.html(html);
            },
            error: function () {
                $clientModalContent.html(
                    '<div class="tav-modal-error" style="color:#dc2626;padding:20px;">Failed to connect to server.</div>'
                );
            },
        });
    });

    // Close client modal on overlay click or × button.
    $(document).on('click', '#tav-client-modal .tav-modal-close, #tav-client-modal .tav-modal-overlay', function () {
        $clientModal.fadeOut(200);
    });

    // Save client status + notes.
    $(document).on('click', '#tav-client-save-btn', function () {
        var $btn       = $(this);
        var clientId   = $btn.data('client-id');
        var status     = $('#tav-client-status').val();
        var notes      = $('#tav-client-notes').val();
        var $saveStatus = $('#tav-client-save-status');

        $btn.prop('disabled', true).text('Saving…');
        $saveStatus.text('').css('color', '');

        $.ajax({
            url:  tavData.ajaxurl,
            type: 'POST',
            data: {
                action:    'tav_save_client_details',
                client_id: clientId,
                status:    status,
                notes:     notes,
                nonce:     tavData.nonce,
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Save Changes');
                if (response.success) {
                    $saveStatus.text('Saved!').css('color', '#16a34a');
                    setTimeout(function () { $saveStatus.text(''); }, 3000);
                } else {
                    $saveStatus.text(
                        (response.data && response.data.message) ? response.data.message : 'Error saving.'
                    ).css('color', '#dc2626');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Save Changes');
                $saveStatus.text('Network error — please try again.').css('color', '#dc2626');
            },
        });
    });

    // Escape key closes mobile nav or whichever modal is open.
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if ($wrap.hasClass('is-mobile-nav-open')) {
                tavCloseMobileNav();
                return;
            }
            if ($modal.is(':visible'))       $modal.fadeOut(200);
            if ($clientModal.is(':visible')) $clientModal.fadeOut(200);
        }
    });
});
