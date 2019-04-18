$(function() {
    function url_from_name(name) {
        if (typeof name != 'undefined') {
            name = name.toLowerCase();
            name = name.replace(/[^a-z0-9_.~:]+/g, '-');
            name = name.replace(/:/g, '/');
            return name;
        }
    }

    var auto_url = url_from_name($('.input-name input').val());
    $('.input-name input').on('input', function() {
        var $url = $('.input-url input');
        if ($url.val() == auto_url) {
            auto_url = url_from_name(this.value);
            $url.val(auto_url);
        }
    });
});
