jQuery(document).ready(function( $ ) {
	$('#mpw_htaccess_notice_dismiss').click(function(event) {
		event.preventDefault();

		$.post(ajaxurl, {'action': 'mpw_dismiss_htaccess_notice', 'nonce': mpw_htaccess.nonce});

		$('#mpw_htaccess_notice').hide();
	});
});
