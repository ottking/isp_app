/**
 * OTTKING Admin — main.js
 * All AJAX, DataTable, Modal functions (English UI)
 */

// ─── Flusonic server list cache ───────────────────────────
let _flServersCache = [];

// ─── DataTable helper ─────────────────────────────────────
function initDT(id) {
    if ($.fn.DataTable.isDataTable(id)) {
        $(id).DataTable().destroy();
    }
    return $(id).DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        language: {
            search:     'Search:',
            lengthMenu: 'Show _MENU_ entries',
            paginate:   { next: 'Next', previous: 'Prev' },
            info:       'Showing _START_–_END_ of _TOTAL_ entries',
            emptyTable: 'No data available',
        }
    });
}

// ─── Load functions ───────────────────────────────────────
function loadChannels() {
    $.get('api.php?get=ch', function(data) {
        $('#channel-list-container').html(data);
        let t = initDT('#chTable');
        $('#ch-count').text(t.rows().count());
        _buildCatDropdown();
    });
}

function loadCategories() {
    $.get('api.php?get=cat', function(data) {
        $('#category-list-container').html(data);
        let t = initDT('#catTable');
        $('#cat-count').text(t.rows().count());
        _buildCatDropdown();
    });
}

function loadNotifications() {
    $.get('api.php?get=notify', function(data) {
        $('#notify-list-container').html(data);
        let t = initDT('#notTable');
        $('#not-count').text(t.rows().count());
    });
}

function loadFlServers() {
    $.get('api.php?get=fl_servers', function(data) {
        $('#fl-server-container').html(data);
        let t = initDT('#flTable');
        $('#fl-count').text(t.rows().count());
    });
}

function loadUsers() {
    $.get('api.php?get=user', function(data) {
        $('#user-list-container').html(data);
        initDT('#userTable');
    });
}

// ─── Category dropdown builder ────────────────────────────
function _buildCatDropdown() {
    let options = '<option value="">Select Category</option>';
    if ($.fn.DataTable.isDataTable('#catTable')) {
        $('#catTable tbody tr').each(function() {
            let name = $(this).find('td:eq(2)').text().trim();
            let uid  = $(this).find('td:eq(3)').text().trim().replace(/`/g, '');
            if (uid) options += `<option value="${uid}">${name}</option>`;
        });
    }
    $('#ch_cat').html(options);
}

// ─── Flusonic server list loader for channel modal ────────
function _loadFlServerDropdown(selectedServerId) {
    $.get('api.php?get=fl_servers_json', function(res) {
        try {
            _flServersCache = typeof res === 'string' ? JSON.parse(res) : res;
        } catch(e) {
            _flServersCache = [];
        }

        let opts = '<option value="">— Select Flusonic Server —</option>';
        _flServersCache.forEach(function(s) {
            let sel = (selectedServerId && String(s.id) === String(selectedServerId)) ? 'selected' : '';
            opts += `<option value="${s.id}" data-url="${s.server_url}" data-ip="${s.server_ip}" ${sel}>${s.label} (${s.server_ip})</option>`;
        });
        $('#fl_server_select').html(opts);
        _updateFlBasePreview();
    }).fail(function() {
        $('#fl_server_select').html('<option value="">— No servers configured —</option>');
    });
}

function _updateFlBasePreview() {
    let opt = $('#fl_server_select option:selected');
    let url = opt.data('url') || '';
    if (url && !url.endsWith('/')) url += '/';
    $('#fl_base_url_preview').text(url || 'http://server/');
}

// ─── Stream type toggle ───────────────────────────────────
function _applyStreamType(type) {
    if (type === 'flusonic') {
        $('#flusonic-fields').show();
        $('#external-fields').hide();
        $('#token_field_wrap').show();
        // Disable external input so it's not submitted
        $('#ch_url_external').prop('disabled', true);
        $('#ch_url_flusonic').prop('disabled', false);
    } else {
        $('#flusonic-fields').hide();
        $('#external-fields').show();
        // Token not applicable for external
        $('#ch_token').val('no');
        $('#token_field_wrap').hide();
        $('#ch_url_external').prop('disabled', false);
        $('#ch_url_flusonic').prop('disabled', true);
    }
}

$(document).on('change', 'input[name="stream_type"]', function() {
    _applyStreamType($(this).val());
});

$(document).on('change', '#fl_server_select', function() {
    _updateFlBasePreview();
});

// ─── Channel modal: intercept submit to build URL ─────────
$(document).on('submit', '#chForm', function(e) {
    let type = $('input[name="stream_type"]:checked').val();

    if (type === 'flusonic') {
        let serverId  = $('#fl_server_select').val();
        let streamSlug = $('#fl_stream_slug').val().trim();

        if (!serverId) {
            e.preventDefault();
            e.stopImmediatePropagation();
            Swal.fire('Missing Server', 'Please select a Flusonic server.', 'warning');
            return false;
        }
        if (!streamSlug) {
            e.preventDefault();
            e.stopImmediatePropagation();
            Swal.fire('Missing Slug', 'Please enter the Flusonic stream slug.', 'warning');
            return false;
        }

        // Build the full stream URL: server_url + / + stream_slug + /index.m3u8
        let opt       = $('#fl_server_select option:selected');
        let serverUrl = (opt.data('url') || '').replace(/\/$/, '');
        let fullUrl   = serverUrl + '/' + streamSlug + '/index.m3u8';
        $('#ch_url_flusonic').val(fullUrl);

        // Also store flusonic server id for reference
        $('#ch_url_flusonic').attr('name', 'url');
    } else {
        // External — rename url_external to url
        $('#ch_url_external').attr('name', 'url');
    }
    // Let .ajax-form handler take over
});

// ─── AJAX Form Submit ─────────────────────────────────────
$(document).on('submit', '.ajax-form', function(e) {
    e.preventDefault();
    let form = $(this);
    let btn  = form.find('button[type="submit"]');
    let act  = form.find('input[name="action"]').val();
    let orig = btn.html();

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing...');

    $.post('api.php', form.serialize(), function(res) {
        btn.prop('disabled', false).html(orig);

        if (res.includes('Success') || res.includes('সফল')) {
            Swal.fire({
                title: 'Saved!', text: res, icon: 'success',
                timer: 1800, showConfirmButton: false
            });
            if (act !== 'update_settings') {
                $('.modal').modal('hide');
                loadChannels();
                loadCategories();
                loadNotifications();
                if (act === 'save_fl_server' || act === 'del') loadFlServers();
                loadUsers();
            }
        } else {
            Swal.fire('Error!', res, 'error');
            btn.prop('disabled', false).html(orig);
        }
    }).fail(function() {
        btn.prop('disabled', false).html(orig);
        Swal.fire('Error', 'Server not responding!', 'error');
    });
});

// ─── Delete ───────────────────────────────────────────────
function del(type, id) {
    Swal.fire({
        title: 'Are you sure?',
        text:  'This action cannot be undone!',
        icon:  'warning',
        showCancelButton:     true,
        confirmButtonColor:   '#d33',
        cancelButtonColor:    '#6c757d',
        confirmButtonText:    'Yes, delete!',
        cancelButtonText:     'Cancel',
    }).then(result => {
        if (result.isConfirmed) {
            $.post('api.php', { action: 'del', type, id, csrf_token: CSRF }, function(res) {
                if (res.includes('Success') || res.includes('সফল')) {
                    Swal.fire({ title: 'Deleted!', text: res, icon: 'success', timer: 1500, showConfirmButton: false });
                    loadChannels(); loadCategories(); loadNotifications(); loadFlServers(); loadUsers();
                } else {
                    Swal.fire('Error!', res, 'error');
                }
            });
        }
    });
}

// ─── Channel modal ────────────────────────────────────────
function openChModal() {
    $('#ch_id').val('');
    $('#chModal form')[0].reset();
    // Reset stream type to flusonic
    $('#st_flusonic').prop('checked', true);
    _applyStreamType('flusonic');
    _buildCatDropdown();
    _loadFlServerDropdown(null);
    $('#ch_url_flusonic').attr('name', 'url');
    $('#ch_url_external').attr('name', 'url_external');
    $('#chModal').modal('show');
}

function editCh(data) {
    $('#ch_id').val(data.id);
    $('#ch_name').val(data.channel_name);
    $('#ch_slug').val(data.channel_slug);
    $('#ch_logo').val(data.logo);
    _buildCatDropdown();
    setTimeout(() => $('#ch_cat').val(data.category_id), 100);
    $('#ch_status').val(data.status);
    $('#ch_token').val(data.token_action);

    // Detect stream type by checking if URL matches a flusonic server
    // We'll try to detect if it's a known flusonic server URL
    let url = data.channel_url || '';

    // Always reset names first
    $('#ch_url_flusonic').attr('name', 'url');
    $('#ch_url_external').attr('name', 'url_external');

    // Load Flusonic servers then figure out type
    $.get('api.php?get=fl_servers_json', function(res) {
        try { _flServersCache = typeof res === 'string' ? JSON.parse(res) : res; }
        catch(e) { _flServersCache = []; }

        let opts = '<option value="">— Select Flusonic Server —</option>';
        let matchedServer = null;
        let streamSlug    = '';

        _flServersCache.forEach(function(s) {
            let sUrl = (s.server_url || '').replace(/\/$/, '');
            if (url.startsWith(sUrl + '/')) {
                matchedServer = s;
                // Extract slug: remove server_url prefix and /index.m3u8 suffix
                streamSlug = url.replace(sUrl + '/', '').replace(/\/index\.m3u8(\?.*)?$/, '');
            }
            opts += `<option value="${s.id}" data-url="${s.server_url}" data-ip="${s.server_ip}">${s.label} (${s.server_ip})</option>`;
        });

        $('#fl_server_select').html(opts);

        if (matchedServer) {
            // It's a Flusonic stream
            $('#st_flusonic').prop('checked', true);
            _applyStreamType('flusonic');
            $('#fl_server_select').val(matchedServer.id);
            $('#fl_stream_slug').val(streamSlug);
            $('#ch_url_flusonic').val(url);
            _updateFlBasePreview();
        } else {
            // It's an external stream
            $('#st_external').prop('checked', true);
            _applyStreamType('external');
            $('#ch_url_external').val(url).attr('name', 'url');
            $('#ch_url_flusonic').attr('name', 'url_dummy');
        }
    }).fail(function() {
        $('#st_external').prop('checked', true);
        _applyStreamType('external');
        $('#ch_url_external').val(url).attr('name', 'url');
    });

    $('#chModal').modal('show');
}

// ─── Category modal ───────────────────────────────────────
function openCatModal() {
    $('#cat_pk').val('');
    $('#catModal form')[0].reset();
    $('#catModal').modal('show');
}
function editCat(data) {
    $('#cat_pk').val(data.id);
    $('#cat_name').val(data.cat_name);
    $('#cat_uid').val(data.cat_id);
    $('#cat_order').val(data.ordering || '');
    $('#catModal').modal('show');
}

// ─── Notification modal ───────────────────────────────────
function openNotifyModal() {
    $('#notifyModal form')[0].reset();
    $('#notify_id').val('');
    $('#notify_submit').html('<i class="fa fa-paper-plane me-2"></i>Send');
    $('#notifyModal').modal('show');
}
function editNotify(data) {
    $('#notify_id').val(data.id);
    $('#notify_title').val(data.title);
    $('#notify_msg').val(data.msg);
    if (data.end_time) {
        $('#notify_end').val(new Date(data.end_time).toISOString().slice(0, 16));
    } else {
        $('#notify_end').val('');
    }
    $('#notify_submit').html('<i class="fa fa-sync me-2"></i>Update');
    $('#notifyModal').modal('show');
}

// ─── Flusonic Server modal ────────────────────────────────
function openFlModal() {
    $('#fl_id').val('');
    $('#flModal form')[0].reset();
    $('#fl_key').attr('placeholder', 'Enter Secret Key');
    $('#flModal').modal('show');
}
function editFlServer(data) {
    $('#fl_id').val(data.id);
    $('#fl_label').val(data.label);
    $('#fl_ip').val(data.server_ip);
    $('#fl_url').val(data.server_url);
    $('#fl_key').val('').attr('placeholder', 'Leave blank to keep existing key');
    $('#fl_status').val(data.status);
    $('#flModal').modal('show');
}
function toggleFlKey() {
    let inp  = $('#fl_key');
    let icon = $('#fl_key_icon');
    if (inp.attr('type') === 'password') {
        inp.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        inp.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}
function genFlKey() {
    let chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let key   = Array.from(crypto.getRandomValues(new Uint8Array(40)))
                    .map(b => chars[b % chars.length]).join('');
    $('#fl_key').attr('type', 'text').val(key);
    $('#fl_key_icon').removeClass('fa-eye').addClass('fa-eye-slash');
}

// ─── User modals ──────────────────────────────────────────
function openUserModal() {
    $('#userModal form')[0].reset();
    $('#userModal').modal('show');
}
function editUser(id, name) {
    $('#mypass_id').val(id);
    $('#myPassModal input[readonly]').val(name);
    $('#myPassModal h5').text(name + ' — Reset Password');
    $('#myPassModal').modal('show');
}
function openMyPassModal() {
    $('#myPassModal h5').text('Change Password');
    $('#myPassModal').modal('show');
}

// ─── Mobile drawer auto-close ─────────────────────────────
$(document).on('click', '#mobile-menu .nav-link', function() {
    let el = document.getElementById('menuDrawer');
    let bs = bootstrap.Offcanvas.getInstance(el);
    if (bs) bs.hide();
});

// ─── Page ready ───────────────────────────────────────────
$(document).ready(function() {
    loadCategories();
    loadChannels();
});
