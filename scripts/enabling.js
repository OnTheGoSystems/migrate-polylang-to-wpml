jQuery(document).ready(function( $ ) {
	$('#migrate_polylang_to_wpml_confirm_db_backup').change(function() {
		if (this.checked) {
			$('#migrate_polylang_wpml').prop('disabled', false);
		} else {
			$('#migrate_polylang_wpml').prop('disabled', true);
		}
	});
	
	$('.remove_polylang_data_accept').change(function() {
		
		var acc1 = $('#remove_polylang_data_accept_1').prop('checked');
		var acc2 = $('#remove_polylang_data_accept_2').prop('checked');
		if ( acc1 && acc2 ) {
			$('#remove_polylang_data').prop('disabled', false);
		} else {
			$('#remove_polylang_data').prop('disabled', true);
		}
	});
	
});