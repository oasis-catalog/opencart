$(document).ready(function () {
    $("#tree").Tree();
});

$('#input-no-vat').click(function () {
    if ($(this).is(':checked')) {
        $('#tax-class').show(100);
    } else {
        $('#tax-class').hide(100);
    }
});

$('#rating-group input:checkbox').click(function () {
    if ($(this).is(':checked')) {
        $('#rating-group input:checkbox').not(this).prop('checked', false);
    }
});

// Cope task
var texti = document.getElementById('input-cron-product');
var btni = document.getElementById('copy-cron-product');
var textu = document.getElementById('input-cron-stock');
var btnu = document.getElementById('copy-cron-stock');

btni.onclick = function () {
    texti.select();
    document.execCommand("copy");
}
btnu.onclick = function () {
    textu.select();
    document.execCommand("copy");
}

// Progress bar
setTimeout(upAjaxProgressBar, 20000);

function upAjaxProgressBar() {
    jQuery(function ($) {
        $.ajax({
            url: 'index.php?route=extension/oasiscatalog/module/oasis|get_data_progress_bar&user_token=' + user_token,
            type: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response) {
                    if ('step_item' in response) {
                        document.getElementById('upAjaxStep').style.width = response.step_item + '%';
                        $('#upAjaxStep').html(response.step_item + '%');
                    }

                    if ('total_item' in response) {
                        document.getElementById('upAjaxTotal').style.width = response.total_item + '%';
                        $('#upAjaxTotal').html(response.total_item + '%');
                    }

                    if ('progress_icon' in response) {
                        document.querySelector(".oasis-process-icon").innerHTML = response.progress_icon;
                    }

                    if ('progress_step_text' in response) {
                        document.querySelector('.oasis-process-text').innerHTML = response.progress_step_text;
                    }

                    if ('status_progress' in response) {
                        if (response.status_progress == true) {
                            addAnimatedBar('progress-bar-striped progress-bar-animated');
                            setTimeout(upAjaxProgressBar, 5000);
                        } else {
                            removeAnimatedBar('progress-bar-striped progress-bar-animated');
                            setTimeout(upAjaxProgressBar, 60000);
                        }
                    }
                } else {
                    removeAnimatedBar('progress-bar-striped progress-bar-animated');
                    setTimeout(upAjaxProgressBar, 600000);
                }
            }
        });
    });
}

function addAnimatedBar(classStr) {
    let lassArr = classStr.split(' ');

    lassArr.forEach(function (item, index, array) {
        let upAjaxTotal = document.getElementById('upAjaxTotal');

        if (upAjaxTotal && !upAjaxTotal.classList.contains(item)) {
            upAjaxTotal.classList.add(item);
        }

        let upAjaxStep = document.getElementById('upAjaxStep');

        if (upAjaxStep && !upAjaxStep.classList.contains(item)) {
            upAjaxStep.classList.add(item);
        }
    });
}

function removeAnimatedBar(classStr) {
    let lassArr = classStr.split(' ');

    lassArr.forEach(function (item, index, array) {
        let upAjaxTotal = document.getElementById('upAjaxTotal');

        if (upAjaxTotal && upAjaxTotal.classList.contains(item)) {
            upAjaxTotal.classList.remove(item);
        }

        let upAjaxStep = document.getElementById('upAjaxStep');

        if (upAjaxStep && upAjaxStep.classList.contains(item)) {
            upAjaxStep.classList.remove(item);
        }
    });
}
