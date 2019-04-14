$(function() {
    $('textarea.markdown').each(function() {
        new SimpleMDE({
            element: this,
        });
    });
});
