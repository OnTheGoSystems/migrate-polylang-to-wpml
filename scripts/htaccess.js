jQuery(document).ready(function( $ ) {
	$('#mpw_htaccess_notice_dismiss').click(function(event) {
		event.preventDefault();
		
		var d = new Date();
		d.setTime(d.getTime() + (200*24*60*60*1000));
		var expires = "expires="+ d.toUTCString();
		
		document.cookie = "mpw_htaccess_notice_dismiss=1; " + expires;
		
		$('#mpw_htaccess_notice').hide();
		
	});
});