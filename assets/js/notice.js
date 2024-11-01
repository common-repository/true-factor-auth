/* global tfa_main, jQuery */

jQuery(function ($) {
    var wrapper;

    window.tfaNoticeAutoHide = function () {
        [].forEach.call(tfaGetNoticeWrapper().querySelectorAll('.tfa-notice'), function (n) {
            if (n._autoHideTimeout) {
                return;
            }
            n._autoHideTimeout = setTimeout(function () {
                $(n).slideUp();
            }, 5000);
        });
    };

    window.tfaGetNoticeWrapper = function () {
        if (wrapper) {
            return wrapper;
        }
        wrapper = document.querySelector('tfa-notices');
        if (wrapper) {
            return wrapper;
        }
        wrapper = document.createElement('div');
        wrapper.className = 'tfa-notices';
        wrapper.addEventListener('click', function (ev) {
            $(wrapper).children().slideUp();
        });
        document.body.appendChild(wrapper);
        return wrapper;
    };

    window.tfaShowNotice = function (msg, cls) {
        if (typeof msg !== 'string') {
            msg = msg.msg;
            cls = msg.cls;
        }
        var msgEl = document.createElement('div');
        msgEl.className = 'tfa-notice ' + cls;
        msgEl.innerHTML = msg;
        tfaGetNoticeWrapper().appendChild(msgEl);
        tfaNoticeAutoHide();
    };

    window.addEventListener('load', function () {
        if (!window.tfa_main || !tfa_main.notices) {
            return;
        }
        tfa_main.notices.forEach(tfaShowNotice);
        tfaNoticeAutoHide();
    });
});