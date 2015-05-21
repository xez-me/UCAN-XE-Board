(function($) {
	var token = $('meta[name=UCANBoard-CSRFToken]').attr('content');
	if (!token) return;

	$.ajaxSetup({headers: { 'X-XE-UCAN-CSRFToken': token }});
})(jQuery);
