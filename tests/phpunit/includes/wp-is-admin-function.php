<?php
/**
 * migrate-polylang-to-wpml.php creates the plugin, and with it every admin hook, as soon
 * as it is included inside wp-admin. The tests include it for the class alone and build
 * their own instances, so this answers that they are not in wp-admin.
 */
function is_admin() {
	return false;
}
