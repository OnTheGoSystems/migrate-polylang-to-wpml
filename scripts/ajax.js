jQuery(document).ready(function( $ ) {
	$('#migrate_polylang_wpml').click(function(event) {
		event.preventDefault();
		
		$('#migrate_polylang_wpml').prop('disabled', true);
		
		$('#mpw_ajax_result').empty().append("<div>"+mpw_ajax_str.mig_start+"</div>");
		$('#mpw_ajax_result').append("<div>"+mpw_ajax_str.lan_start+"</div>");
		$.post(ajaxurl,{'action':'mpw_migrate_languages'})
				.done(function(resp){ 
					$('#mpw_ajax_result').append("<div>"+resp.data.msg+"</div>");
					$('#mpw_ajax_result').append("<div>"+mpw_ajax_str.posts_start+"</div>");
					$.post(ajaxurl,{'action':'mpw_migrate_posts'})
							.done(function(resp) {
								$('#mpw_ajax_result').append("<div>"+resp.data.msg+"</div>");
								$('#mpw_ajax_result').append("<div>"+mpw_ajax_str.tax_start+"</div>");
								$.post(ajaxurl,{'action':'mpw_migrate_taxonomies'})
										.done(function(resp) {
											$('#mpw_ajax_result').append("<div>"+resp.data.msg+"</div>");
											$('#mpw_ajax_result').append("<div>"+mpw_ajax_str.str_start+"</div>");
											$.post(ajaxurl,{'action':'mpw_migrate_strings'})
													.done(function(resp) {
														$('#mpw_ajax_result').append("<div>"+resp.data.msg+"</div>");
														$('#mpw_ajax_result').append("<div>"+mpw_ajax_str.widg_start+"</div>");
														$.post(ajaxurl,{'action':'mpw_migrate_widgets'})
																.done(function(resp) {
																	$('#mpw_ajax_result').append("<div>"+resp.data.msg+"</div>");
																	$('#mpw_ajax_result').append("<div><strong>"+mpw_ajax_str.mig_done+"</strong></div>");
																
																	$('#migrate_polylang_wpml').prop('disabled', false);
																});
													});
										});
							});
				});
	});
});