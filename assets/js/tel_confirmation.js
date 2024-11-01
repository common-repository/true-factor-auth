/* globals tfa_tel_confirmation, jQuery */

(function ($) {

    if (!window.tfaInitTelConfirmation) {

        window.tfaInitTelConfirmation = function ($container) {

            var data_key = 'tfa-verified-tel';

            if (!window.tfa_tel_confirmation) {
                return;
            }

            var $tel_input = $container.find(tfa_tel_confirmation.input_selector);

            if (!$tel_input.length) {
                return;
            }

            if (tfa_tel_confirmation.add_button) {
                var $ctrl = $('<span class="button tfa-tel-verify"></span>');

                if (tfa_tel_confirmation.verified_number) {
                    $tel_input.data(data_key, tfa_tel_confirmation.verified_number);
                }

                function showVerifyButton() {

                    if (!$ctrl) {
                        return;
                    }

                    if ($tel_input.val()) {
                        $ctrl.show();
                        if ($tel_input.val() && $tel_input.val() == $tel_input.data(data_key)) {
                            $ctrl.text(tfa_tel_confirmation.verified_caption);
                            $ctrl.addClass('tfa-tel-verified');
                        } else {
                            $ctrl.addClass('button');
                            $ctrl.text(tfa_tel_confirmation.verify_caption);
                            $ctrl.removeClass('tfa-tel-verified');
                        }
                    } else {
                        $ctrl.hide();
                    }
                }

                $ctrl.insertAfter($tel_input);

                $ctrl.on('click', function () {
                    if (!$tel_input.val() || $tel_input.val() == $tel_input.data(data_key)) {
                        return;
                    }

                    tfaLoadPopup({
                        data: {
                            action: 'tfa_confirm_phone_number',
                            tel: $tel_input.val()
                        }
                    }, {
                        onLoad: function ($content) {
                            if (!$content) {
                                return;
                            }

                            var $dialog_form = $content.find('form');

                            $dialog_form.data('json-submit-success', function (data) {
                                if (data.error) {
                                    tfaShowError(data.error);
                                    return;
                                }
                                if (data.success) {
                                    $tel_input.val(data.tel);
                                    $tel_input.data(data_key, data.tel);
                                    tfaClosePopup($content);
                                }
                            });
                        }
                    });
                });

                $tel_input.on('change keyup', function () {
                    showVerifyButton();
                });

                showVerifyButton();
            }

            window.tfaTelNumberSubmit = function (evt) {
                if ($tel_input.data(data_key) == $tel_input.val()) {
                    return true;
                }

                evt.preventDefault();
                evt.stopPropagation();
                evt.stopImmediatePropagation();
                // In case if form gget disabled after first submission (like Ultimate Member profile form does)
                $form.find('[type=submit]').removeAttr('disabled');

                var dialog_config = {

                    onLoad: function ($content) {
                        var $verify_phone_input = $content.find('[name=tel]');
                        $verify_phone_input.attr('readonly', 'readonly');
                        $verify_phone_input.val($tel_input.val());

                        var $dialog_form = $content.find('form');
                        $dialog_form.on('submit', function (dialog_form_submit_evt) {
                            dialog_form_submit_evt.preventDefault();
                            $.ajax({
                                url: $dialog_form.attr('action'),
                                method: $dialog_form.attr('method'),
                                data: $dialog_form.serialize(),
                                dataType: 'json',
                                success: function (data) {
                                    dialog_config.onSuccess(data);
                                }
                            });
                        })
                    },

                    onSuccess: function (data) {
                        if (data.error) {
                            tfaShowError(data.error);
                            return;
                        }
                        if (data.success) {
                            if (data.tel) {
                                $tel_input.val(data.tel);
                            }
                            $tel_input.data('changed', null);
                            HTMLFormElement.prototype.submit.call($form[0]);
                        }
                    }
                };

                tfaLoadPopup({
                    data: {
                        action: 'tfa_confirm_phone_number',
                        tel: $tel_input.val(),
                    }
                }, dialog_config);

                return false;
            };

            if (tfa_tel_confirmation.require) {
                // When phone number confirmation is required, we disallow form submission.
                var $form = $tel_input.closest('form');

                if ($form.length) {
                    // We should use inline binding to ensure highest priority.
                    $form[0].setAttribute('onsubmit', 'return tfaTelNumberSubmit.call(this, event)');
                }
            }

        }
    }

    if (!window.tfaInitCallbacks) {
        window.tfaInitCallbacks = [];
    }

    tfaInitCallbacks.push(tfaInitTelConfirmation);
})(jQuery);