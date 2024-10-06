jQuery(document).ready(function($) {
    const avatarLists = {};

    function getPostIdFromElement($element) {
        let postId = $element.data('post-id');
        if (!postId) {
            const $childWithPostId = $element.find('[data-post-id]').first();
            if ($childWithPostId.length) {
                postId = $childWithPostId.data('post-id');
            } else {
                const $parentWithPostId = $element.closest('[data-post-id]');
                if ($parentWithPostId.length) {
                    postId = $parentWithPostId.data('post-id');
                }
            }
        }
        return postId;
    }

    function initializeAvatarList($element) {
        const postId = getPostIdFromElement($element);
        if (!postId) {
            console.error('Konnte keine gültige postId für das Element finden:', $element);
            return;
        }
        if (!avatarLists[postId]) {
            avatarLists[postId] = {
                timestamp: 0
            };
            loadAvatarList(postId);
        }
    }

    function loadAvatarList(postId) {
        $.ajax({
            url: group_builder_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_avatar_list',
                post_id: postId,
                timestamp: avatarLists[postId].timestamp
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.updated) {
                        $('.attendees[data-post-id="' + postId + '"]').html(response.data.avatar_list);
                        avatarLists[postId].timestamp = response.data.timestamp;
                    }
                    console.log('Avatar-Liste aktualisiert für Post-ID:', postId, 'Neuer Timestamp:', avatarLists[postId].timestamp);
                } else {
                    console.error('Fehler beim Laden der Avatar-Liste:', response.data ? response.data.message : 'Unbekannter Fehler');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX-Fehler beim Laden der Avatar-Liste:', textStatus, errorThrown);
            }
        });
    }

    // Initialisiere alle vorhandenen Avatar-Listen
    $('.attendees').each(function() {
        initializeAvatarList($(this));
    });

    // Heartbeat-Sender
    $(document).on('heartbeat-send', function(e, data) {
        data.group_builder_request = Object.keys(avatarLists).map(postId => ({
            post_id: postId,
            timestamp: avatarLists[postId].timestamp
        }));
        console.log('Heartbeat-Anfrage gesendet:', data.group_builder_request);
    });

    // Heartbeat-Empfänger
    $(document).on('heartbeat-tick', function(e, data) {
        if (data.group_builder_response) {
            data.group_builder_response.forEach(function(item) {
                if (item.updated) {
                    $('.attendees[data-post-id="' + item.post_id + '"]').html(item.avatar_list);
                    avatarLists[item.post_id].timestamp = item.timestamp;
                    console.log('Avatar-Liste aktualisiert für Post-ID:', item.post_id, 'Neuer Timestamp:', item.timestamp);
                }
            });
        }
    });

    // Event-Handler für die Buttons
    $(document).on('click', '.show-interest, .withdraw-interest, .create-group, .join-group, .leave-group', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = getPostIdFromElement($button);
        if (!postId) {
            console.error('Konnte keine gültige postId für den Button finden:', $button);
            return;
        }
        const action = $button.attr('class').split(' ')[0].replace('-', '_');
        const data = {
            action: action,
            post_id: postId
        };
        if (action === 'join_group' || action === 'leave_group') {
            data.group_id = $button.data('group-id');
        }

        $.ajax({
            url: group_builder_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    console.log('Aktion erfolgreich:', action, 'für Post ID:', postId);
                    //loadAvatarList(postId); // Lade die Liste sofort neu
                    location.reload();
                } else {
                    console.error('Fehler bei der Aktion:', action, 'für Post ID:', postId, response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX-Fehler bei der Aktion:', action, 'für Post ID:', postId, textStatus, errorThrown);
            }
        });
    });

    // Weitere Event-Handler und Funktionen...
// Frontend-Formular Modal
    // für die Bearbeitung von Gruppen und Pinnwandeinträge
    $(document).on('click', '.edit-group', function(e) {
        e.preventDefault();
        const groupId = $(this).data('group-id');
        openModal('#group-edit-modal');
    });

    // Beitrittsoption umschalten
    $(document).on('click', '.toggle-join-option', function(e) {
        e.preventDefault();
        const groupId = $(this).data('group-id');
        const toggle = $(this);
        console.info(groupId,'toggle-join-option');
        $.ajax({
            url: group_builder_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_join_option',
                group_id: groupId
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    // Aktualisiere UI
                    toggle.text(response.data.new_status);
                } else {
                    console.error('Fehler beim Umschalten der Beitrittsoption:', response.data.message);
                }
            }
        });
    });

    /**
     * Funktion zum Kopieren des Einladungstextes in die Zwischenablage
     * @param {string} element_id ID des Textfeldes, dessen Inhalt kopiert werden soll
     */
    function copy_to_clipboard(element_id) {
        // Das Textfeld auswählen
        var textfeld = document.getElementById(element_id);

        // Den Inhalt des Textfeldes auswählen
        textfeld.select();
        textfeld.setSelectionRange(0, 99999); // Für mobile Geräte

        // Den Text in die Zwischenablage kopieren
        navigator.clipboard.writeText(textfeld.value)
            .then(() => {
                alert("Einladungslink wurde in die Zwischenablage kopiert!");
            })
            .catch(err => {
                console.error('Fehler beim Kopieren: ', err);
            });
    }
    // Einladungslink kopieren
    $(document).on('click', '.copy-invite-link', function(e) {
        e.preventDefault();
        copy_to_clipboard('invite-link');
    });

    // Einladung kopieren
    $(document).on('click', '.copy-invite-message', function(e) {
        e.preventDefault();
        $('#invite-message-container').html($('#invite-message').val());
        copy_to_clipboard('invite-message');
    });

    // Einladungslink generieren
    $(document).on('click', '.generate-invite-link', function(e) {
        e.preventDefault();
        const groupId = $(this).data('group-id');

        $.ajax({
            url: group_builder_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_invite_link',
                group_id: groupId
            },
            success: function(response) {
                if (response.success) {
                    // Zeige den generierten Link in einem Modal an
                    $('#invite-link-modal .modal-content').html(`
                        <p>Hier ist der Einladungslink für diese Gruppe:</p>
                        <input type="text" value="${response.data.invite_link}" readonly>
                        <button class="copy-link">Link kopieren</button>
                    `);
                    openModal('#invite-link-modal');
                } else {
                    console.error('Fehler beim Generieren des Einladungslinks:', response.data.message);
                }
            }
        });
    });

    $('.ct-comments-title').html('Beiträge');


    // Todo: Image Pfade für die Menüpunkte anpassen aus dem Pluginpfad ./images/ holen
    // jQuery("a.ct-menu-link:contains('Startseite')")
    //     .css('background-image',"url('/wp-content/uploads/assets/markt.png')")
    //     .css('background-size','50px')
    //     .css('background-repeat', 'no-repeat')
    //     .css('background-position', 'center center')
    //     .css('padding-top','70px');
    // jQuery("a.ct-menu-link:contains('Pinnwand')")
    //     .css('background-image',"url('/wp-content/uploads/assets/pinnwand.png')")
    //     .css('background-size','50px')
    //     .css('background-repeat', 'no-repeat')
    //     .css('background-position', 'center center')
    //     .css('padding-top','70px');
    // jQuery("a.ct-menu-link:contains('Gruppen')")
    //     .css('background-image',"url('/wp-content/uploads/assets/gruppe.png')")
    //     .css('background-size','50px')
    //     .css('background-repeat', 'no-repeat')
    //     .css('background-position', 'center center')
    //     .css('padding-top','70px');
    // jQuery("a.ct-menu-link:contains('Mitglieder')")
    //     .css('background-image',"url('/wp-content/uploads/assets/netzwerk.png')")
    //     .css('background-size','50px')
    //     .css('background-repeat', 'no-repeat')
    //     .css('background-position', 'center center')
    //     .css('padding-top','70px');
    // jQuery("a.ct-menu-link:contains('Dokumente')")
    //     .css('background-image',"url('/wp-content/uploads/assets/library.png')")
    //     .css('background-size','50px')
    //     .css('background-repeat', 'no-repeat')
    //     .css('background-position', 'center center')
    //     .css('padding-top','70px');

    // Datepicker für Event-Start- und Enddatum synchronisieren
    $('.acf-field[data-name="event_start_date"]').on('change',function(e, $el){
        console.log('Event Start Date changed');
        $('.acf-field[data-name="event_start_date"]').find('.input-alt').each(function(el){
            var startdate = $(this).val();
            var enddate = new Date(startdate);
            enddate.setHours(enddate.getHours() + 2);
            $('.acf-field[data-name="event_end_date"] .hasDatepicker').datepicker('setDate', enddate)
        });
    });

    if(group_builder_group.is_member==='no'){
        $('#comments').remove();
    }
});

