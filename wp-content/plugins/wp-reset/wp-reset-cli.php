<?php

/**
 * WP Reset PRO
 * https://wpreset.com/
 * (c) WebFactory Ltd, 2017-2020
 */


// include only file
if (!defined('ABSPATH')) {
  die('Do not open this file directly.');
}


/**
 * Resets & deletes various WP objects including the whole site. Restore points provided through snapshots.
 */
class WP_Reset_CLI extends WP_CLI_Command
{
  /**
   * Reset site options, site or do a nuclear reset.
   *
   * ## OPTIONS
   *
   * <options|site|nuclear>
   * : Type of reset to perform.
   *
   * [--reactivate-theme]
   * : Reactivate currently active theme after site reset.
   *
   * [--reactivate-plugins]
   * : Reactivate all currently active plugins after site reset.
   *
   * [--reactivate-webhooks]
   * : Reactivate WP Webhooks plugin after site reset.
   *
   * [--deactivate-wp-reset]
   * : Deactivate WP Reset after site reset. By default it will stay active after reset.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   * $ wp reset reset options
   * Success: Site options have been reset.
   *
   * * $ wp reset reset site
   * Success: Site has been reset.
   *
   * * $ wp reset reset nuclear
   * Success: Nuclear reset is done.
   *
   * @when after_wp_load
   */
  function reset($args, $assoc_args)
  {
    global $wp_reset, $wp_reset_tools;

    if (empty($args[0])) {
      WP_CLI::error('Please choose a subcommand: options, site or nuclear.');
      return;
    } elseif (false == in_array($args[0], array('options', 'site', 'nuclear'))) {
      WP_CLI::error('Unknown subcommand. Please choose from: options, site or nuclear.');
    } else {
      $subcommand = $args[0];
    }

    switch ($subcommand) {
      case 'options':
        WP_CLI::confirm('Are you sure you want to reset all site\'s options?', $assoc_args);

        // fix for a notice; we overwrite site URL immediately after reset
        if (!isset($_SERVER['HTTP_HOST'])) {
          $_SERVER['HTTP_HOST'] = '';
        }

        $tmp = $wp_reset_tools->do_reset_options();
        if (!is_wp_error($tmp)) {
          WP_CLI::success('Site options have been reset.');
        } else {
          WP_CLI::error('Unable to reset site options. ' . $tmp->get_error_message());
        }
        break;
      case 'site':
        $params = array();
        if (!empty($assoc_args['reactivate-theme'])) {
          $params['reactivate_theme'] = true;
        }
        if (!empty($assoc_args['disable-wp-reset'])) {
          $params['reactivate_wpreset'] = false;
        } else {
          $params['reactivate_wpreset'] = true;
        }
        if (!empty($assoc_args['reactivate-plugins'])) {
          $params['reactivate_plugins'] = true;
        }
        if (!empty($assoc_args['reactivate-webhooks'])) {
          $params['reactivate_webhooks'] = true;
        }

        WP_CLI::confirm('Are you sure you want to reset the site?', $assoc_args);

        $result = $wp_reset->do_reinstall($params);
        if (is_wp_error($result)) {
          WP_CLI::error($result->get_error_message());
        } else {
          WP_CLI::success('Site has been reset.');
        }
        break;
      case 'nuclear':
        WP_CLI::confirm('Are you sure you want to do a nuclear reset and remove all custom files & reset the site?', $assoc_args);
        WP_CLI::success('Starting process ...');
        WP_CLI::runcommand('reset delete themes --yes', array('launch' => false));
        WP_CLI::runcommand('reset delete plugins --yes', array('launch' => false));
        WP_CLI::runcommand('reset delete uploads --yes', array('launch' => false));
        WP_CLI::runcommand('reset delete wp-content --yes', array('launch' => false));
        WP_CLI::runcommand('reset reset site --yes', array('launch' => false));
        WP_CLI::success('Nuclear reset is done.');
        break;
      default:
        // should never come to this but can't hurt
        WP_CLI::error('Unknown subcommand. Please choose from: options, site or nuclear.');
        return;
    }
  } // reset


  /**
   * Display WP Reset version.
   *
   * @when after_wp_load
   */
  function version($args, $assoc_args)
  {
    global $wp_reset;

    WP_CLI::line('WP Reset PRO v' . $wp_reset->version);
  } // version


  /**
   * Delete or reset selected WordPress objects.
   *
   * ## OPTIONS
   *
   * <plugins|themes|transients|uploads|custom-tables|htaccess|theme-options|cache|widgets|wp-content>
   * : WP objects to delete or reset.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * [--empty]
   * : Empty (truncate) custom tables instead of deleting (dropping) them.
   *
   * ## EXAMPLES
   *
   * $ wp reset delete themes --yes
   * Success: 3 themes have been deleted.
   *
   * $ wp reset delete custom-tables --truncate --yes
   * Success: 3 custom tables have been emptied.
   *
   * $ wp reset delete htaccess --yes
   * Success: Htaccess file has been deleted.
   *
   * @when after_wp_load
   */
  function delete($args, $assoc_args)
  {
    global $wp_reset, $wp_reset_tools, $wpdb;

    if (empty($args[0])) {
      WP_CLI::error('Please choose a subcommand: plugins, themes, transients, uploads, htaccess, custom-tables, cache, widgets, wp-content.');
      return;
    } elseif (false == in_array($args[0], array('themes', 'plugins', 'transients', 'uploads', 'htaccess', 'custom-tables', 'theme-options', 'cache', 'widgets', 'wp-content'))) {
      WP_CLI::error('Unknown subcommand. Please choose from: plugins, themes, transients, uploads, htaccess, custom tables, cache, theme-options, widgets, wp-content.');
    } else {
      $subcommand = $args[0];
    }

    switch ($subcommand) {
      case 'themes':
        WP_CLI::confirm('Are you sure you want to delete all themes?', $assoc_args);
        $cnt = $wp_reset->do_delete_themes(array('keep_default_theme' => false, 'keep_current_theme' => false));
        WP_CLI::success($cnt . ' themes have been deleted.');
        break;
      case 'plugins':
        WP_CLI::confirm('Are you sure you want to delete all plugins?', $assoc_args);
        $wp_reset->do_deactivate_plugins(array('keep_wp_reset' => true, 'silent_deactivate' => false));
        $cnt = $wp_reset->do_delete_plugins(array('keep_wp_reset' => true));
        WP_CLI::success($cnt . ' plugins have been deleted.');
        break;
      case 'transients':
        WP_CLI::confirm('Are you sure you want to delete all transients?', $assoc_args);
        $cnt = $wp_reset->do_delete_transients();
        WP_CLI::success($cnt . ' transient database entries have been deleted.');
        break;
      case 'uploads':
        WP_CLI::confirm('Are you sure you want to delete all files & folders in /uploads/ folder?', $assoc_args);
        $cnt = $wp_reset->do_delete_uploads();
        WP_CLI::success($cnt . ' files & folders have been deleted.');
        break;
      case 'custom-tables':
        if (!empty($assoc_args['empty'])) {
          WP_CLI::confirm('Are you sure you want to empty (truncate) all custom tables (prefix: ' . $wpdb->prefix . ')?', $assoc_args);
          $cnt = $wp_reset->do_truncate_custom_tables(array('tables' => '__all'));
          WP_CLI::success($cnt . ' custom tables have been emptied.');
        } else {
          WP_CLI::confirm('Are you sure you want to delete (drop) all custom tables (prefix: ' . $wpdb->prefix . ')?', $assoc_args);
          $cnt = $wp_reset->do_drop_custom_tables(array('tables' => '__all'));
          WP_CLI::success($cnt . ' custom tables have been deleted.');
        }
        break;
      case 'htaccess':
        WP_CLI::confirm('Are you sure you want to delete the .htaccess file?', $assoc_args);
        $tmp = $wp_reset->do_delete_htaccess();
        if (!is_wp_error($tmp)) {
          WP_CLI::success('Htaccess file has been deleted.');
        } else {
          WP_CLI::error('Htaccess file has not been deleted. ' . $tmp->get_error_message());
        }
        break;
      case 'theme-options':
        WP_CLI::confirm('Are you sure you want to reset all options (mods) for all themes?', $assoc_args);
        $cnt = $wp_reset->do_reset_theme_options();
        WP_CLI::success('Options for ' . $cnt . ' themes have been reset.');
        break;
      case 'cache':
        WP_CLI::confirm('Are you sure you want to purge all cache?', $assoc_args);
        $tmp = $wp_reset_tools->do_purge_cache();
        WP_CLI::success('Cache has been purged.');
        break;
      case 'widgets':
        WP_CLI::confirm('Are you sure you want to delete all widgets?', $assoc_args);
        $tmp = $wp_reset_tools->do_delete_widgets();
        WP_CLI::success('All widgets have been deleted.');
        break;
      case 'wp-content':
        WP_CLI::confirm('Are you sure you want to delete all content in /wp-content/ folder except for default folders (languages, mu-plugins, plugins, themes, uploads)?', $assoc_args);
        $cnt = $wp_reset->do_delete_wp_content();
        WP_CLI::success($cnt . ' files & folders have been deleted.');
        break;
      default:
        // should never come to this but can't hurt
        WP_CLI::error('Unknown subcommand. Please choose from: plugins, themes, transients, uploads, htaccess, custom-tables or theme-options.');
        return;
    }
  } // delete


  /**
   * List and manipulate DB snapshots.
   *
   * ## OPTIONS
   *
   * <list|create|restore|export|delete>
   * : Action to perform with snapshot.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * [--id=<snapshot-id>]
   * : Specify snapshot ID when doing restore, export and delete.
   *
   * [--name=<snapshot-name>]
   * : When creating a new snapshot specify an optional name.
   *
   * ## EXAMPLES
   *
   * wp reset snapshots create --yes
   * Success: New snapshot with ID 089bea has been created.
   *
   * $ wp reset snapshots delete --id=123456
   * Success: Snapshot has been deleted.
   *
   * $ wp reset snapshots export --id=123456
   * Success: Snapshot has been exported and saved to: https://test.site/wp-content/wp-reset-snapshots-export/wp-reset-snapshot-123456.sql.gz
   *
   * @when after_wp_load
   */
  function snapshots($args, $assoc_args)
  {
    global $wp_reset;

    if (empty($args[0])) {
      WP_CLI::error('Please choose a subcommand: list, create, restore, export or delete.');
      return;
    } elseif (false == in_array($args[0], array('list', 'create', 'restore', 'export', 'delete'))) {
      WP_CLI::error('Unknown subcommand. Please choose from: list, create, restore, export or delete.');
    } else {
      $subcommand = $args[0];
    }

    switch ($subcommand) {
      case 'list':
        if ($snapshots = $wp_reset->get_snapshots()) {
          $table = array();
          foreach ($snapshots as $ss) {
            $tmp = array();
            $tmp['id'] = $ss['uid'];
            if (!empty($ss['name'])) {
              $tmp['name'] = $ss['name'];
            } else {
              $tmp['name'] = 'n/a';
            }
            $tmp['created'] = date(get_option('date_format'), strtotime($ss['timestamp'])) . ' @ ' . date(get_option('time_format'), strtotime($ss['timestamp']));
            $tmp['info'] = $ss['tbl_core'] . ' standard & ';
            if ($ss['tbl_custom']) {
              $tmp['info'] .= $ss['tbl_custom'] . ' custom table' . ($ss['tbl_custom'] == 1 ? '' : 's');
            } else {
              $tmp['info'] .= 'no custom tables';
            }
            $tmp['info'] .= ' totaling ' . WP_Reset_Utility::format_size($ss['tbl_size']) . ' in ' . number_format($ss['tbl_rows']) . ' rows';

            $table[] = $tmp;
          } // foreach
          WP_CLI\Utils\format_items('table', $table, array('id', 'name', 'created', 'info'));
        } else {
          WP_CLI::line('There are no saved snapshots.');
        }
        break;
      case 'create':
        if (!empty($assoc_args['name'])) {
          $name = trim($assoc_args['name']);
        } else {
          $name = '';
        }

        WP_CLI::confirm('Are you sure you want to create a new snapshot?', $assoc_args);
        $new = $wp_reset->do_create_snapshot(array('name' => $name));
        if (is_wp_error($new)) {
          WP_CLI::error($new->get_error_message());
        } else {
          WP_CLI::success('New snapshot with ID ' . $new['uid'] . ' has been created.');
        }
        break;
      case 'restore':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reset snapshots list" to get a list of all snapshots.');
          break;
        } else {
          WP_CLI::confirm('Are you sure you want to restore the site to the snapshot with ID ' . $assoc_args['id'] . '?', $assoc_args);
          $restore = $wp_reset->do_restore_snapshot($assoc_args['id']);
          if (is_wp_error($restore)) {
            WP_CLI::error($restore->get_error_message());
          } else {
            WP_CLI::success('Site has been restored to the selected snapshot.');
          }
        }
        break;
      case 'export':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reset snapshots list" to get a list of all snapshots.');
          break;
        } else {
          $export = $wp_reset->do_export_snapshot($assoc_args['id']);
          if (is_wp_error($export)) {
            WP_CLI::error($export->get_error_message());
          } else {
            $url = content_url() . '/' . $wp_reset->snapshots_folder . '/' . $export;
            WP_CLI::success('Snapshot has been exported and saved to: ' .  $url);
          }
        }
        break;
      case 'delete':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reset snapshots list" to get a list of all snapshots.');
          break;
        } else {
          WP_CLI::confirm('Are you sure you want to delete the snapshot with ID ' . $assoc_args['id'] . '?', $assoc_args);
          $del = $wp_reset->do_delete_snapshot($assoc_args['id']);
          if (is_wp_error($del)) {
            WP_CLI::error($del->get_error_message());
          } else {
            WP_CLI::success('Snapshot has been deleted.');
          }
        }
        break;
      default:
        // it should never come to this but can't hurt
        WP_CLI::error('Unknown subcommand. Please choose from: list, create, restore, export or delete.');
        return;
    }
  } // snapshots


  /**
   * This command is no longer available. Please use "wp reset snapshots create" instead.
   */
  function backups($args, $assoc_args)
  {
    WP_CLI::error('This command is no longer available. Please use "wp reset snapshots create".');
  } // backups
} // WP_Reset_CLI

WP_CLI::add_command('reset', 'WP_Reset_CLI');
