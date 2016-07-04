jQuery(document).ready(function( $ ) {
	$('div#message.otgs-is-dismissible').helpcursor({
		position: 'top',
		color: '#666666',
		msg: tooltips_texts.before_mig
	});
	
	$('#icl_initial_language p:nth-of-type(1)').helpcursor({
		position: 'bottom',
		color: '#666666',
		msg: tooltips_texts.default_language
	});
	
	$('#lang-sec-1 h3').helpcursor({
		position: 'top',
		color: '#666666',
		msg: tooltips_texts.additional_languages
	});
	
	
});