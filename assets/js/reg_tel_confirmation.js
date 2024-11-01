/* globals tfa_reg_tel_confirmation, jQuery */

/**
 * This function must be called on form submission after client-side validation, but before sending data to server.
 *
 * @param evt
 * @returns {boolean}
 */
function tfaRegTelConfirmationSubmitForm(evt) {
    var form = evt.target;
    var data_key_done = 'tfa_reg_tel';

    if (form.dataset[data_key_done]) {
        // Phone number confirmed, allow submitting.
        return true;
    }

    evt.preventDefault();
    evt.stopPropagation();
    evt.stopImmediatePropagation();


    var input = form.querySelector('input[name=' + form.dataset.tfaTelInput + ']');
    if (!input) {
        input = tfaAddHidden(form, form.dataset.tfaTelInput);
    }

    var popup_config = {

        onLoad: function ($content) {
            var $dialog_form = $content.find('form');
            $dialog_form.on('submit', function (dialog_form_submit_evt) {
                dialog_form_submit_evt.preventDefault();
                jQuery.ajax({
                    url: $dialog_form.attr('action'),
                    method: $dialog_form.attr('method'),
                    data: $dialog_form.serialize(),
                    dataType: 'json',
                    success: function (data) {
                        popup_config.onSuccess(data);
                    }
                });
            });

            var submit = form.querySelector('[type=submit]');
            if (submit && submit.getAttribute('disabled')) {
                submit.removeAttribute('disabled');
            }
        },

        onSuccess: function (data) {
            if (data.error) {
                tfaShowError(data.error);
                return;
            }

            if (data.tel) {
                input.value = data.tel;
                form.dataset[data_key_done] = data.tel;
                HTMLFormElement.prototype.submit.call(form);
            }
        }
    };

    tfaLoadPopup({
        data: {
            action: 'tfa_reg_confirm_phone_number',
            tel: input.value,
        }
    }, popup_config);

    return false;
}

(function ($) {
    if (window.tfaInitRegTelConfirmation) {
        return;
    }

    window.tfaInitRegTelConfirmation = function ($container) {
        if (!tfa_reg_tel_confirmation || !tfa_reg_tel_confirmation.selectors) {
            return;
        }

        var selectors = tfa_reg_tel_confirmation.selectors.split('\n');

        while (selectors.length) {
            var selector = selectors.splice(0, 3);
            var form = $container[0].querySelector(selector[0]);
            if (!form) {
                continue;
            }

            form.dataset.tfaTelInput = selector[1];

            // Attach the handler as inline attribute to ensure that it is fired prior to any other handlers.
            form.setAttribute('onsubmit', 'tfaRegTelConfirmationSubmitForm(event)');
        }
    };

    if (!window.tfaInitCallbacks) {
        window.tfaInitCallbacks = [];
    }

    tfaInitCallbacks.push(tfaInitRegTelConfirmation);
})(jQuery);