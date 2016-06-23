# Migrate Polylang to WPML
Import multilingual data from Polylang to WPML

# Installation and usage
- Do the database backup! There is a reason why this plugin has version number 0.1
- Deactivate Polylang
- Activate WPML Multilingual Blog/CMS and this plugin (Migrate Polylang to WPML). If you want to migrate also string translations, activate WPML String Translation plugin
- Finish WPML Wizard (you will see it right after activation)
- if you have custom post types, go to WPML > Translation options (or WPML > Translation Management > Multilingual Content Setup) and set those types to be translatable.
- Go to Tools > Migrate from Polylang to WPML
- Click button "Migrate" and wait a little (depends on migrated content's size)
- Done. You can uninstall this plugin

# What this plugin does 
- Sets in WPML same languages available as they were in Polylang
- Migrates all your posts, pages and custom post types: sets correct language and joins them together to be translation of each other
- Migrates all your taxonomies (categories, tags and custom taxonomies)
- Migrates your strings (only those which has been translated in Polylang and WPMl String Translation recognized and registered as correct string)
- Migrate widgets: you need activate [WPML Widgets](https://wordpress.org/plugins/wpml-widgets/) plugin

# What this plugin doesn't (yet)
- Migrate widgets: after migration you will have to go to Apperance > Widgets and adjust their settings manually
- Migrate menus: please use WPML standard option to synchronise menus
- Migrate other settings: this will be implemented progressively, for time being you are free to adjust them manually after migration

# Support and feedback
- please report any issue or feature request at [WPML forum ](https://wpml.org/forums/forum/english-support/)
