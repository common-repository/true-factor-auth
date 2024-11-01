/* globals tfa_main, jQuery */

function tfaAddHidden(container, name, val, before) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    if (typeof val != 'undefined') {
        input.value = val;
    }
    if (before) {
        jQuery(input).insertBefore(container);
    } else {
        jQuery(input).appendTo(container);
    }
    return input;
}

(function ($) {

    if (!window.tfaInitCallbacks) {
        window.tfaInitCallbacks = [];
    }

    window.tfaInit = function ($container) {
        tfaInitJsonForm($container);
        tfaInitQrCode($container);
        tfaInitCallbacks.forEach(function (callback) {
            callback($container);
        });
        $container.find('.tfa-focus').focus();
    };

    var $doc = $(document);

    $doc.ready(function () {

        $doc.on('click', '.tfa-popup-link', function (evt) {
            var $t = $(this);
            evt.preventDefault();
            tfaLoadPopup({
                url: $t.data('popup-url')
            }, $t.data('popup-config'));
        });

        $doc.on('click', '.tfa-popup-wrapper', function (evt) {
            if (evt.target !== this) {
                return;
            }
            var $t = $(this);
            if ($t.hasClass('tfa-modal')) {
                return;
            }
            $t.remove();
        });

        $doc.on('click', '.tfa-popup-close', function (evt) {
            if (this.closest('.tfa-modal')) {
                return;
            }
            tfaClosePopup(this);
        });

        tfaInit($doc);
    });

    if (!window.tfaShow2FAPopup) {
        window.tfaShow2FAPopup = function (params, callback, ajax_config) {
            ajax_config = Object.assign({
                url: tfa_main.ajax_url,
                dataType: "json",
                data: Object.assign({
                    action: 'tfa_popup'
                }, params),
                success: function (data) {
                    if (typeof data != 'object' || data.skip) {
                        return callback();
                    }
                    if (data.error) {
                        tfaShowError(data.error, 'error');
                        return;
                    }

                    data.onLoad = function ($content) {
                        var $form = $content.find('form');
                        $form.on('submit', function (evt) {
                            evt.preventDefault();
                            var $submit = $form.find('[type=submit]');
                            $.ajax({
                                dataType: "json",
                                method: 'post',
                                url: $form.attr('action'),
                                data: $form.serialize(),
                                success: function (data) {
                                    // Allow re-submit.
                                    $submit.removeAttr('disabled');

                                    if (data.error || data.error_message) {
                                        tfaShowError(data.error || data.error_message);
                                        return;
                                    }

                                    if (data.message) {
                                        tfaShowMessage(data.message);
                                        return;
                                    }

                                    if (!data.token) {
                                        return;
                                    }

                                    tfaClosePopup($content);

                                    if (data.popup) {
                                        tfaPopup({
                                            content: data.popup,
                                            popupCallback: function () {
                                                if (typeof callback == 'function') {
                                                    callback(data.token);
                                                }
                                            }
                                        });
                                        return;
                                    }

                                    if (typeof callback == 'function') {
                                        callback(data.token);
                                    }
                                }
                            });
                            $submit.attr('disabled', 'disabled');
                        });
                    };

                    tfaPopup(data);
                }
            }, ajax_config || {});
            $.ajax(ajax_config);
        };
    }

    if (!window.tfaPopup) {
        /**
         * Display popup window.
         * This function can be overridden in your theme.
         * Use of jQuery UI Dialog is not required.
         *
         * Make sure to call config.onLoad() after displaying the dialog.
         * Make sure to override the tfaClosePopup function if you don't use jQuery UI Dialog.
         *
         * @param {*} data
         * - content The content of the popup. You can also provide a custom function here.
         *   If content starts with 'function', the function will be executed.
         * - onLoad The callback function to be called when popup is shown.
         */
        window.tfaPopup = function (data) {
            if (data.error) {
                tfaShowError(data.error);

                return;
            }

            if (data.message) {
                tfaShowMessage(data.message);
                return;
            }

            if (data.skip) {
                if (typeof data.onSuccess == 'function') {
                    data.onSuccess(data);
                }
                return;
            }

            if (!data.content) {
                return;
            }

            if (typeof data.content == 'string') {
                var callbackId = addCallback(data.popupCallback);
                if (data.popupCallback) {
                    data.content = data.content.replace('true_factor_auth_popup_callback', 'tfaCallback(' + callbackId + ')');
                }
                if (data.content.substring(0, 8) === 'function') {
                    // Execute the function and exit.
                    eval('var fn = ' + data.content);
                    fn(data);
                    return;
                }
                if (data.title) {
                    data.content = '<div class="tfa-popup-title">' + data.title + '</div>' + data.content;
                }
            }

            var $content = $('<div class="tfa-popup-wrapper"><div class="tfa-popup">' + data.content + '</div></div>');

            $content.appendTo(document.body);
            if (data.popupClass) {
                $content.addClass(data.popupClass);
            }

            tfaInit($content);

            if (typeof data.onLoad == 'function') {
                data.onLoad($content);
            }
        };
    }

    if (!window.tfaClosePopup) {
        /**
         * Display popup window.
         * This function can be overridden in your theme.
         *
         * Make sure to override the tfaPopup.
         *
         * @param $content The dialog content element wrapped with jQuery wrapper. To access the HTMLElement itself, use `$content[0]`.
         */
        window.tfaClosePopup = function ($content) {
            var $wrapper = $($content).closest('.tfa-popup-wrapper') || $content;
            $wrapper.remove();
        }
    }

    if (!window.tfaShowMessage) {
        /**
         * Override this function to show custom messages.
         *
         * @param message
         * @param options
         */
        window.tfaShowMessage = function (message, options) {
            if (!options) {
                options = {};
            }

            if (!options.content) {
                options.content = '<div class="tfa-popup-text">' + message + '</div>'
            }

            if (options) {
                if (options.footerCloseButton) {
                    options.content += '<div class="tfa-popup-footer"><button type="button" class="button button-cancel tfa-popup-close tfa-focus">' + (tfa_main.t_close || 'Close') + '</button></div>';
                }
            }

            tfaPopup(options);
        };
    }

    if (!window.tfaShowError) {
        /**
         * Override this function to show custom error messages.
         * @param message
         */
        window.tfaShowError = function (message) {
            return tfaShowMessage(message, {
                title: tfa_main.t_error || 'Error',
                popupClass: 'error',
                footerCloseButton: true
            });
        };
    }

    window.tfaLoadPopup = function (ajax_config, popup_config) {
        $.ajax(Object.assign({
            url: tfa_main.ajax_url,
            dataType: "json",
            success: function (data) {
                tfaPopup(Object.assign(data, popup_config || {}));
            }
        }, ajax_config));
    };

    /**
     * Dummy callback.
     */
    window.true_factor_auth_popup_callback = function () {
        // pass.
    };

    var callbacks = [];

    function addCallback(fn) {
        callbacks.push(fn);
        return callbacks.length - 1;
    }

    window.tfaCallback = function (id) {
        return callbacks[id];
    };

    if (!window.tfaInitJsonForm) {
        window.tfaInitJsonForm = function ($container) {
            var $form = $container.find('.tfa-json-form');

            if (!$form.length) {
                return;
            }

            var $submit = $form.find('[type=submit]');

            $form.on('submit', function (evt) {
                evt.preventDefault();

                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    dataType: "json",
                    data: $form.serialize(),

                    success: function (data) {
                        $submit.removeAttr('disabled');

                        if (data.error || data.error_message) {
                            tfaShowError(data.error || data.error_message);
                            return;
                        }

                        if (data.message) {
                            tfaShowMessage(data.message);
                        }

                        if (data.popup) {
                            tfaPopup({content: data.popup});
                        }

                        if (data.success) {
                            tfaClosePopup($container);
                            if ($form.hasClass('tfa-json-form-reload-on-success')) {
                                location.reload();
                            }
                        }

                        if (data.redirect) {
                            location.href = data.redirect;
                        }
                    },

                    complete: function () {
                        $submit.removeAttr('disabled');
                    }
                });

                $submit.attr('disabled', 'disabled');
            });
        }
    }

    if (!window.tfaInitQrCode) {
        window.tfaInitQrCode = function ($container) {
            $container.find('.js-qrcode').each(function (i, el) {
                $(el).qrcode({
                    render: 'image',
                    text: $(el).data('qr'),
                    size: $(el).width() || 200
                });
            })
        }
    }

})(jQuery);