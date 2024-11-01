/* global tfa_main, tfa_rules, jQuery */

(function ($) {

    window.tfaActionsGetAction = function (id) {
        return tfa_rules.actions.find(function (act) {
            return +act.id === +id;
        });
    };

    window.tfaInitShortCodes = function ($container) {
        $container.find('.tfa-shortcode-action').each(function (i, el) {
            var $c = $(el);
            var $form = $c.find('form');
            var action_id = $c.data('action-id');
            $form.on('submit', function (evt) {
                if ($form.data('tf-done')) {
                    return;
                }
                evt.preventDefault();
                evt.stopPropagation();
                evt.stopImmediatePropagation();
                tfaShow2FAPopup({
                    id: action_id
                }, function (val) {
                    tfaAddHidden($form, tfa_rules.token_key + action_id, val);
                    $form.data('tf-done', 1);
                    $form.trigger('submit');
                });
            });
        });
    };

    window.tfaInitActionTriggers = function ($container) {
        // Attach event handlers to buttons matching CSS selectors.
        if ('undefined' == typeof tfa_rules) {
            return;
        }
        tfa_rules.actions.forEach(function (action) {
            [].forEach.call($container[0].querySelectorAll(action.button_selector), function (ctrl) {
                var $ctrl = $(ctrl);
                $ctrl.data('tfa-action-id', action.id);
                if (action.pre_callback) {
                    $ctrl.data('tfa-pre-callback', action.pre_callback);
                }
                if ($ctrl[0].tagName === 'FORM') {
                    $ctrl.data('tfa-onsubmit', $ctrl.attr('onsubmit'));
                    // Attach event handler via attribute to ensure highest priority of our handler.
                    $ctrl.attr('onsubmit', 'tfaTriggerAction(event, this)');
                } else {
                    $ctrl.data('tfa-onclick', $ctrl.attr('onclick'));
                    // Attach event handler via attribute to ensure highest priority of our handler.
                    $ctrl.attr('onclick', 'tfaTriggerAction(event, this)');
                }
            });
        });
    };

    window.tfaTriggerAction = function (event, ctrl) {
        var $ctrl = $(ctrl);
        if ($ctrl[0].tagName === 'FORM') {
            if ($ctrl.data('tf-done')) {
                if ($ctrl.data('tfa-onsubmit')) {
                    eval($ctrl.data('tfa-onsubmit'));
                }
                return;
            }
        } else {
            if ($ctrl.data('tf-done')) {
                if ($ctrl.data('tfa-onclick')) {
                    eval($ctrl.data('tfa-onclick'));
                }
                return;
            }
        }

        event.preventDefault();

        if ($ctrl.data('tfa-pre-callback')) {
            var pre_result;
            try {
                eval('pre_result = ' + $ctrl.data('tfa-pre-callback'));
                if (typeof pre_result === 'function') {
                    pre_result = pre_result();
                }
                if (pre_result === false) {
                    // Probably validation failed, no need to proceed.
                    return;
                }
            } catch (e) {
                console.error(e);
            }
        }

        var action_id = $ctrl.data('tfa-action-id');

        tfaShow2FAPopup({
            id: action_id
        }, function (val) {
            var $frm;

            if ($ctrl[0].tagName === 'FORM') {
                $frm = $ctrl;
            } else {
                $frm = $ctrl.closest('form');
            }

            if ($frm.length) {
                tfaAddHidden($ctrl, tfa_rules.token_key + action_id, val, true);
            }

            $ctrl.data('tf-done', 1);
            if ($ctrl[0].tagName === 'FORM') {
                HTMLFormElement.prototype.submit.call($ctrl[0]);
            } else {
                $ctrl.click();
            }
        });
    };

    // Initialization.

    if (!window.tfaInitCallbacks) {
        window.tfaInitCallbacks = [];
    }

    window.tfaInitCallbacks.push(tfaInitShortCodes);
    window.tfaInitCallbacks.push(tfaInitActionTriggers);
})
(jQuery);