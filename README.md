# Migrate Polylang to WPML
Import multilingual data from Polylang to WPML

# Installation and usage
- Backup your database! There is a reason why this plugin has the version number 0.1
- Deactivate Polylang.
- Activate both WPML Multilingual Blog/CMS and the (Migrate Polylang to WPML) plugins. If you also want to migrate string translations, activate WPML String Translation plugin.
- Finish the WPML Wizard (you will see it right after activation)
- If you have custom post types, go to WPML > Translation options (or WPML > Translation Management > Multilingual Content Setup) and set those types to be translatable.
- Go to Tools > Migrate from Polylang to WPML
- Click button "Migrate" and wait until the migration process is completed (it might take longer based on the size of the content you are migrating)
- Done. You can uninstall the (Migrate Polylang to WPML) plugin.

# What this plugin does 
- Configures WPML to have the same languages as they were in Polylang.
- Migrates all your posts, pages and custom post types and their translations.
- Migrates all your taxonomies (categories, tags and custom taxonomies) and their translations.
- Migrates your strings (only those which has been translated in Polylang and WPMl String Translation recognized and registered as correct string)
- Migrate widgets: You will need to activate [WPML Widgets](https://wordpress.org/plugins/wpml-widgets/) plugin.

# What this plugin doesn't (yet)
- Migrate menus: Use the  menu synchronization between languages option in WPML.
- Migrate other settings: This will be implemented progressively. For the time being, you are free to adjust them manually after the migration process is completed.

# Support and feedback
- please report any issue or feature request at [WPML forum ](https://wpml.org/forums/forum/english-support/)
- please check [Wiki page](https://github.com/OnTheGoSystems/migrate-polylang-to-wpml/wiki) for known issues
