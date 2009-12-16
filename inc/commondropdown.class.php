<?php
/*
 * @version $Id: document.class.php 9112 2009-10-13 20:17:16Z moyo $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Remi Collet
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

/// CommonDropdown class - generic dropdown
abstract class CommonDropdown extends CommonDBTM {

   // For delete operation (entity will overload this value)
   public $must_be_replace = false;

   /**
    * Return Additional Fileds for this type
    */
   function getAdditionalFields() {
      return array();
   }

   function defineTabs($ID,$withtemplate) {
      global $LANG;

      $ong=array();
      $ong[1] = $this->getTypeName();
      return $ong;
   }

  /**
   * Have I the right to "create" the Object
   *
   * MUST be overloaded for entity_dropdown
   *
   * @return booleen
   **/
   function canCreate() {
      return haveRight('dropdown','w');
   }

   /**
   * Have I the right to "view" the Object
   *
   * MUST be overloaded for entity_dropdown
   *
   * @return booleen
   **/
   function canView() {
      return haveRight('dropdown','r');
   }


   /**
    * Display content of Tab
    *
    * @param $ID of the item
    * @param $tab number of the tab
    *
    * @return true if handled (for class stack)
    */
   function showTabContent ($ID, $tab) {
      if ($ID>0) {
         switch ($tab) {
            case -1 :
               Plugin::displayAction($this, $tab);
               return false;

            default :
               return Plugin::displayAction($this, $tab);
         }
      }
      return false;
   }

   /**
    * Display title above search engine
    *
    * @return nothing (HTML display if needed)
    */
   function title() {
      Dropdown::showItemTypeMenu(Dropdown::getStandardDropdownItemTypes(), $_SERVER['PHP_SELF']);
   }

   function displayHeader () {
      commonHeader($this->getTypeName(),$_SERVER['PHP_SELF'],"config","dropdowns",get_class($this));
   }

   function showForm ($target,$ID) {
      global $CFG_GLPI, $LANG;

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w');
      }

      $this->showTabs($ID, '',getActiveTab($this->type),array('itemtype'=>$this->type));
      $this->showFormHeader($target,$ID,'',2);

      $fields = $this->getAdditionalFields();
      $nb=count($fields);

      echo "<tr class='tab_bg_1'><td>".$LANG['common'][16]."&nbsp;:</td>";
      echo "<td>";
      echo "<input type='hidden' name='itemtype' value='".$this->type."'>";
      autocompletionTextField("name",$this->table,"name",$this->fields["name"],40);
      echo "</td>";

      echo "<td rowspan='".($nb+1)."'>";
      echo $LANG['common'][25]."&nbsp;:</td>";
      echo "<td rowspan='".($nb+1)."'>
            <textarea cols='45' rows='".($nb+2)."' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>\n";

      foreach ($fields as $field) {
         echo "<tr class='tab_bg_1'><td>".$field['label']."&nbsp;:</td><td>";
         switch ($field['type']) {
            case 'dropdownUsersID' :
               User::dropdownUsersID($field['name'], $this->fields[$field['name']], "interface", 1,
                                $this->fields["entities_id"]);
               break;

            case 'dropdownValue' :
               Dropdown::dropdownValue(getTableNameForForeignKeyField($field['name']),
                              $field['name'], $this->fields[$field['name']],1,
                              $this->fields["entities_id"]);
               break;

            case 'text' :
               autocompletionTextField($field['name'],$this->table,$field['name'],
                                       $this->fields[$field['name']],40);
               break;

            case 'parent' :
               if ($field['name']=='entities_id') {
                  $restrict = -1;
               } else {
                  $restrict = $this->fields["entities_id"];
               }
               Dropdown::dropdownValue($this->table, $field['name'],
                             $this->fields[$field['name']], 1, $restrict, '',
                             ($ID>0 ? getSonsOf($this->table, $ID) : array()));
               break;

            case 'icon' :
               Dropdown::dropdownIcons($field['name'],
                             $this->fields[$field['name']],
                             GLPI_ROOT."/pics/icones");
               if (!empty($this->fields[$field['name']])) {
                  echo "&nbsp;<img style='vertical-align:middle;' alt='' src='".
                       $CFG_GLPI["typedoc_icon_dir"]."/".$this->fields[$field['name']]."'>";
               }
               break;

            case 'bool' :
               Dropdown::showYesNo($field['name'], $this->fields[$field['name']]);
               break;
         }
         echo "</td></tr>\n";
      }

      $candel=true;
      if (isset($this->fields['is_protected']) && $this->fields['is_protected']) {
         $candel=false;
      }
      $this->showFormButtons($ID,'',2,$candel);

      echo "<br><div id='tabcontent'></div>";
      echo "<script type='text/javascript'>loadDefaultTab();</script>";

      return true;
   }

   function pre_deleteItem($id) {
      if (isset($this->fields['is_protected']) && $this->fields['is_protected']) {
         return false;
      }
      return true;
   }
   /**
    * Get search function for the class
    *
    * @return array of search option
    */
   function getSearchOptions() {
      global $LANG;

      $tab = array();
      $tab['common']           = $LANG['common'][32];;

      $tab[1]['table']         = $this->table;
      $tab[1]['field']         = 'name';
      $tab[1]['linkfield']     = '';
      $tab[1]['name']          = $LANG['common'][16];
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_link'] = $this->type;

      $tab[16]['table']     = $this->table;
      $tab[16]['field']     = 'comment';
      $tab[16]['linkfield'] = 'comment';
      $tab[16]['name']      = $LANG['common'][25];
      $tab[16]['datatype']  = 'text';

      if ($this->entity_assign) {
         $tab[80]['table']     = 'glpi_entities';
         $tab[80]['field']     = 'completename';
         $tab[80]['linkfield'] = 'entities_id';
         $tab[80]['name']      = $LANG['entity'][0];
      }
      if ($this->maybeRecursive()) {
         $tab[86]['table']     = $this->table;
         $tab[86]['field']     = 'is_recursive';
         $tab[86]['linkfield'] = 'is_recursive';
         $tab[86]['name']      = $LANG['entity'][9];
         $tab[86]['datatype']  = 'bool';
      }
      return $tab;
   }

   /** Check if the dropdown $ID is used into item tables
    *
    * @param $ID integer : value ID
    *
    * @return boolean : is the value used ?
    */
   function isUsed() {
      global $DB;

      $ID = $this->fields['id'];

      $RELATION = getDbRelations();
      if (isset ($RELATION[$this->table])) {
         foreach ($RELATION[$this->table] as $tablename => $field) {
            if ($tablename[0]!='_') {
               if (!is_array($field)) {
                  $query = "SELECT COUNT(*) AS cpt
                            FROM `$tablename`
                            WHERE `$field` = '$ID'";
                  $result = $DB->query($query);
                  if ($DB->result($result, 0, "cpt") > 0) {
                     return true;
                  }
               } else {
                  foreach ($field as $f) {
                     $query = "SELECT COUNT(*) AS cpt
                               FROM `$tablename`
                               WHERE `$f` = '$ID'";
                     $result = $DB->query($query);
                     if ($DB->result($result, 0, "cpt") > 0) {
                        return true;
                     }
                  }
               }
            }
         }
      }
      return false;
   }

   /**
    * Report if a dropdown have Child
    * Used to (dis)allow delete action
    */
   function haveChildren() {
      return false;
   }

   /**
    * Show a dialog to Confirm delete action
    * And propose a value to replace
    *
    * @param $target string URL
    *
    *
    */
   function showDeleteConfirmForm($target) {
      global $DB, $LANG,$CFG_GLPI;

      if ($this->haveChildren()) {
         echo "<div class='center'><p class='red'>" . $LANG['setup'][74] . "</p></div>";
         return false;
      }

      $ID = $this->fields['id'];

      echo "<div class='center'>";
      echo "<p class='red'>" . $LANG['setup'][63] . "</p>";

      if (!$this->must_be_replace) {
         // Delete form (set to 0)
         echo "<p>" . $LANG['setup'][64] . "</p>";
         echo "<form action='$target' method='post'>";
         echo "<table class='tab_cadre'><tr><td>";
         echo "<input type='hidden' name='id' value='$ID'/>";
         echo "<input type='hidden' name='forcedelete' value='1'/>";
         echo "<input class='button' type='submit' name='delete' value='".$LANG['buttons'][2]."'/></td>";
         echo "<td><input class='button' type='submit' name='annuler' value='".$LANG['buttons'][34]."'/>";
         echo "</td></tr></table>\n";
         echo "</form>";
      }

      // Replace form (set to new value)
      echo "<p>" . $LANG['setup'][65] . "</p>";
      echo "<form action='$target' method='post'>";
      echo "<table class='tab_cadre'><tr><td>";

      if ($this instanceof CommonTreeDropdown) {
         // TreeDropdown => default replacement is parent
         $fk=getForeignKeyFieldForTable($this->table);
         Dropdown::dropdownValue($this->table, '_replace_by', $this->fields[$fk], 1,
                       $this->getEntityID(), '', getSonsOf($this->table, $ID));
      } else {
         Dropdown::dropdownValue($this->table, '_replace_by', 0, 1, $this->getEntityID(),'',array($ID));
      }
      echo "<input type='hidden' name='id' value='$ID'/>";
      echo "</td><td>";
      echo "<input class='button' type='submit' name='replace' value='".$LANG['buttons'][39]."'/>";
      echo "</td><td>";
      echo "<input class='button' type='submit' name='annuler' value='".$LANG['buttons'][34]."' /></td>";
      echo "</tr></table>\n";
      echo "</form>";
      echo "</div>";
   }

   /** Replace a dropdown item (this) by another one (newID)  and update all linked fields
    * @param $new integer ID of the replacement item
   function replace($newID) {
      global $DB,$CFG_GLPI;

      $oldID = $this->fields['id'];

      $RELATION = getDbRelations();

      if (isset ($RELATION[$this->table])) {
         foreach ($RELATION[$this->table] as $table => $field) {
            if ($table[0]!='_') {
               if (!is_array($field)) {
                  // Manage OCS lock for items - no need for array case
                  if ($table=="glpi_computers" && $CFG_GLPI['use_ocs_mode']) {
                     $query = "SELECT `id`
                               FROM `glpi_computers`
                               WHERE `is_ocs_import` = '1'
                                     AND `$field` = '$oldID'";
                     $result=$DB->query($query);
                     if ($DB->numrows($result)) {
                        if (!function_exists('OcsServer::mergeOcsArray')) {
                           include_once (GLPI_ROOT . "/inc/ocsng.function.php");
                        }
                        while ($data=$DB->fetch_array($result)) {
                           OcsServer::mergeOcsArray($data['id'],array($field),"computer_update");
                        }
                     }
                  }
                  $query = "UPDATE
                            `$table`
                            SET `$field` = '$newID'
                            WHERE `$field` = '$oldID'";
                  $DB->query($query);
               } else {
                  foreach ($field as $f) {
                     $query = "UPDATE
                               `$table`
                               SET `$f` = '$newID'
                               WHERE `$f` = '$oldID'";
                     $DB->query($query);
                  }
               }
            }
         }
      }
   }
    */



}

?>