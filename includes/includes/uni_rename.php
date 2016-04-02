<?php

global $planetrow, $template, $uni_row, $debug, $uni_galaxy, $uni_system;

$classLocale = classLocale::$lang;

try
  {
    $template = gettemplate('universe_rename', true);

    $uni_galaxy = sys_get_param_int('galaxy', $planetrow['galaxy']);
    $uni_system = sys_get_param_int('system');

    if($uni_galaxy < 1 || $uni_galaxy > Vector::$knownGalaxies)
    {
      throw new exception(classLocale::$lang['uni_msg_error_wrong_galaxy'], ERR_ERROR);
    }

    if($uni_system < 0 || $uni_system > Vector::$knownSystems)
    {
      throw new exception(classLocale::$lang['uni_msg_error_wrong_system'], ERR_ERROR);
    }

    $uni_row = db_universe_get($uni_galaxy, $uni_system);
    $uni_row['universe_price'] += $uni_system ? classSupernova::$config->uni_price_system : classSupernova::$config->uni_price_galaxy;
    $uni_row['universe_name'] = strip_tags($uni_row['universe_name'] ? $uni_row['universe_name'] : ($uni_system ? "{$classLocale['sys_system']} [{$uni_galaxy}:{$uni_system}]" : "{$classLocale['sys_galaxy']} {$uni_galaxy}"));

    if(sys_get_param_str('uni_name_submit'))
    {
      $uni_row['universe_name'] = strip_tags(sys_get_param_str('uni_name'));

      $uni_price = sys_get_param_float('uni_price');
      if($uni_price < $uni_row['universe_price'])
      {
        throw new exception(classLocale::$lang['uni_msg_error_low_price'], ERR_ERROR);
      }
      $uni_row['universe_price'] = $uni_price;

      sn_db_transaction_start();
      $user = db_user_by_id($user['id'], true);
      // if($user[get_unit_param(RES_DARK_MATTER, P_NAME)] < $uni_price)
      if(mrc_get_level($user, null, RES_DARK_MATTER) < $uni_price)
      {
        throw new exception(classLocale::$lang['uni_msg_error_no_dm'], ERR_ERROR);
      }

      if(!rpg_points_change($user['id'], RPG_RENAME, -$uni_price, "Renaming [{$uni_galaxy}:{$uni_system}] to " . sys_get_param_str_unsafe('uni_name')))
      {
        throw new exception(classLocale::$lang['sys_msg_err_update_dm'], ERR_ERROR);
      }

      db_universe_rename($uni_galaxy, $uni_system, $uni_row);
      $debug->warning(sprintf(classLocale::$lang['uni_msg_admin_rename'], $user['id'], $user['username'], $uni_price, $uni_system ? classLocale::$lang['uni_system_of'] : classLocale::$lang['uni_galaxy_of'], $uni_galaxy, $uni_system ? ":{$uni_system}" : '', strip_tags(sys_get_param_str_unsafe('uni_name'))), classLocale::$lang['uni_naming'], LOG_INFO_UNI_RENAME);
      sn_db_transaction_commit();
      sys_redirect("galaxy.php?mode=name&galaxy={$uni_galaxy}&system={$uni_system}");
    }
  }
  catch (exception $e)
  {
    sn_db_transaction_rollback();
    $template->assign_block_vars('result', array(
      'STATUS'  => in_array($e->getCode(), array(ERR_NONE, ERR_WARNING, ERR_ERROR)) ? $e->getCode() : ERR_ERROR,
      'MESSAGE' => $e->getMessage()
    ));
  }

  $template->assign_vars(array(
    'GALAXY' => $uni_galaxy,
    'SYSTEM' => $uni_system,

    'NAME'   => sys_safe_output($uni_row['universe_name']),
    'PRICE'  => $uni_row['universe_price'],

    'PAGE_HINT'   => classLocale::$lang['uni_name_page_hint'],
  ));

  display($template, classLocale::$lang['sys_universe'] . ' - ' . classLocale::$lang['uni_naming'], true, '', false);
