jQuery(document).ready(function( $ ) {
	$('#migrate_polylang_to_wpml_confirm_db_backup').change(function() {
		if (this.checked) {
			$('#migrate_polylang_wpml').prop('disabled', false);
		} else {
			$('#migrate_polylang_wpml').prop('disabled', true);
		}
	});
});