<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2017 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

/** @file
* @brief
*/

/**
 * Update from 9.1 to 9.2
 *
 * @return bool for success (will die for most error)
**/
function update91to92() {
   global $DB, $migration, $CFG_GLPI;

   $current_config   = Config::getConfigurationValues('core');
   $updateresult     = true;
   $ADDTODISPLAYPREF = [];

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '9.2'));
   $migration->setVersion('9.2');

   // add business criticity
   $migration->addField("glpi_infocoms", "businesscriticities_id", "integer");
   $migration->migrationOneTable('glpi_infocoms');
   $migration->addKey("glpi_infocoms", "businesscriticities_id");

   if (!$DB->tableExists("glpi_businesscriticities")) {
      $query = "CREATE TABLE `glpi_businesscriticities` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `entities_id` int(11) NOT NULL DEFAULT '0',
        `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
        `comment` text COLLATE utf8_unicode_ci,
        `date_mod` datetime DEFAULT NULL,
        `date_creation` datetime DEFAULT NULL,
        `businesscriticities_id` int(11) NOT NULL DEFAULT '0',
        `completename` text COLLATE utf8_unicode_ci,
        `level` int(11) NOT NULL DEFAULT '0',
        `ancestors_cache` longtext COLLATE utf8_unicode_ci,
        `sons_cache` longtext COLLATE utf8_unicode_ci,
        PRIMARY KEY (`id`),
        KEY `name` (`name`),
        KEY `unicity` (`businesscriticities_id`,`name`),
        KEY `date_mod` (`date_mod`),
        KEY `date_creation` (`date_creation`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "Add business criticity table");
   }

   // Issue #1250 - Add decimal to monitor size
   $migration->changeField('glpi_monitors', 'size', 'size', 'DECIMAL(5,2) NOT NULL DEFAULT "0"');

   //Make software license type a tree dropdown
   $migration->addField("glpi_softwarelicensetypes", "softwarelicensetypes_id", "integer");
   $migration->addField("glpi_softwarelicensetypes", "level", "integer");
   $migration->addField("glpi_softwarelicensetypes", "ancestors_cache", "longtext");
   $migration->addField("glpi_softwarelicensetypes", "sons_cache", "longtext");
   $migration->addField("glpi_softwarelicensetypes", "entities_id", "integer");
   $migration->addField("glpi_softwarelicensetypes", "is_recursive", "bool");
   $tree = $migration->addField("glpi_softwarelicensetypes", "completename", "text");
   $migration->migrationOneTable('glpi_softwarelicensetypes');
   $migration->addKey("glpi_softwarelicensetypes", "softwarelicensetypes_id");

   //First time the dropdown is changed from CommonDropdown to CommonTreeDropdown
   if ($tree) {
      $query = "UPDATE `glpi_softwarelicensetypes`
                SET `completename`=`name`,
                    `is_recursive`='1'";
      $DB->queryOrDie($query, "9.2 make glpi_softwarelicensetypes a tree dropdown");
   }

   // give READ right on components to profiles having UPDATE right
   $query = "UPDATE `glpi_profilerights`
             SET `rights` = `rights` | " . READ . "
             WHERE (`rights` & " . UPDATE .") = '" . UPDATE ."'
                   AND `name` = 'device'";
   $DB->queryOrDie($query, "grant READ right on components to profiles having UPDATE right");

   $migration->displayMessage(sprintf(__('Add of - %s to database'), 'Knowbase item link to tickets'));
   if (!$DB->tableExists('glpi_knowbaseitems_items')) {
      $query = "CREATE TABLE `glpi_knowbaseitems_items` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `knowbaseitems_id` int(11) NOT NULL,
                 `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
                 `items_id` int(11) NOT NULL DEFAULT '0',
                 `date_creation` datetime DEFAULT NULL,
                 `date_mod` datetime DEFAULT NULL,
                 PRIMARY KEY (`id`),
                 UNIQUE KEY `unicity` (`itemtype`,`items_id`,`knowbaseitems_id`),
                 KEY `itemtype` (`itemtype`),
                 KEY `item_id` (`items_id`),
                 KEY `item` (`itemtype`,`items_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_knowbaseitems_items");
   }

   $migration->displayMessage(sprintf(__('Add of - %s to database'), 'Knowbase item revisions'));
   if (!$DB->tableExists('glpi_knowbaseitems_revisions')) {
      $query = "CREATE TABLE `glpi_knowbaseitems_revisions` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `knowbaseitems_id` int(11) NOT NULL,
                 `revision` int(11) NOT NULL,
                 `name` text COLLATE utf8_unicode_ci,
                 `answer` longtext COLLATE utf8_unicode_ci,
                 `language` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `users_id` int(11) NOT NULL DEFAULT '0',
                 `date_creation` datetime DEFAULT NULL,
                 PRIMARY KEY (`id`),
                 UNIQUE KEY `unicity` (`knowbaseitems_id`, `revision`, `language`),
                 KEY `revision` (`revision`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_knowbaseitems_revisions");
   }

   $migration->addField("glpi_knowbaseitemtranslations", "users_id", "integer");
   $migration->migrationOneTable("glpi_knowbaseitemtranslations");
   $migration->addKey("glpi_knowbaseitemtranslations", "users_id");

   //set kb translations users...
   foreach ($DB->request(['SELECT'     => ['glpi_knowbaseitems.id', 'glpi_knowbaseitems.users_id'],
                          'FROM'       => 'glpi_knowbaseitems',
                          'INNER JOIN' => ["glpi_knowbaseitemtranslations"
                                           => ['FKEY' => ['glpi_knowbaseitemtranslations' => 'knowbaseitems_id',
                                                          'glpi_knowbaseitems'            => 'id']]]])
            as $knowitems) {

      $query = "UPDATE `glpi_knowbaseitemtranslations`
                SET `users_id` = '{$knowitems['users_id']}'
                WHERE `knowbaseitems_id` = '{$knowitems['id']}'";
      $DB->queryOrDie($query, 'Set knowledge base translations users');
   }

   $migration->addField("glpi_knowbaseitemtranslations", "date_mod", "DATETIME");
   $migration->addField("glpi_knowbaseitemtranslations", "date_creation", "DATETIME");

   $migration->displayMessage(sprintf(__('Add of - %s to database'), 'Knowbase item comments'));
   if (!$DB->tableExists('glpi_knowbaseitems_comments')) {
      $query = "CREATE TABLE `glpi_knowbaseitems_comments` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `knowbaseitems_id` int(11) NOT NULL,
                 `users_id` int(11) NOT NULL DEFAULT '0',
                 `language` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `comment` text COLLATE utf8_unicode_ci NOT NULL,
                 `parent_comment_id` int(11) DEFAULT NULL,
                 `date_creation` datetime DEFAULT NULL,
                 `date_mod` datetime DEFAULT NULL,
                 PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_knowbaseitems_comments");
   }

   $query = "UPDATE `glpi_profilerights`
             SET `rights` = `rights` | " . KnowbaseItem::COMMENTS ."
             WHERE `name` = 'knowbase'";
   $DB->queryOrDie($query, "9.2 update knowledge base with comment right");

   // add kb category to task categories
   $migration->addField("glpi_taskcategories", "knowbaseitemcategories_id", "integer");
   $migration->migrationOneTable("glpi_taskcategories");
   $migration->addKey("glpi_taskcategories", "knowbaseitemcategories_id");

   // #1476 - Add users_id on glpi_documents_items
   $migration->addField("glpi_documents_items", "users_id", "integer", ['null' => true]);
   $migration->migrationOneTable("glpi_documents_items");
   $migration->addKey("glpi_documents_items", "users_id");
   $migration->addPostQuery("UPDATE `glpi_documents_items`,
                                    `glpi_documents`
                             SET `glpi_documents_items`.`users_id` = `glpi_documents`.`users_id`
                             WHERE `glpi_documents_items`.`documents_id` = `glpi_documents`.`id`",
                            "9.2 update set users_id on glpi_documents_items");

   //add product number
   $product_types = ['Computer',
                     'Printer',
                     'NetworkEquipment',
                     'Phone',
                     'Peripheral',
                     'Monitor'];

   foreach ($product_types as $type) {
      if (class_exists($type . 'Model')) {
         $table = getTableForItemType($type . 'Model');
         $migration->addField($table, 'product_number', 'string', ['after' => 'comment']);
         $migration->migrationOneTable($table);
         $migration->addKey($table, 'product_number');
      }
   }

   // add fields on every item_device tables
   $tables = ['glpi_items_devicecases',
              'glpi_items_devicecontrols',
              'glpi_items_devicedrives',
              'glpi_items_devicegraphiccards',
              'glpi_items_deviceharddrives',
              'glpi_items_devicememories',
              'glpi_items_devicemotherboards',
              'glpi_items_devicenetworkcards',
              'glpi_items_devicepcis',
              'glpi_items_devicepowersupplies',
              'glpi_items_deviceprocessors',
              'glpi_items_devicesoundcards'];

   //add serial, location and state on each devices items
   foreach ($tables as $table) {
      $migration->addField($table, "otherserial", "varchar(255) NULL DEFAULT NULL");
      $migration->addField($table, "locations_id", "int(11) NOT NULL DEFAULT '0'");
      $migration->addField($table, "states_id", "int(11) NOT NULL DEFAULT '0'");
      $migration->migrationOneTable($table);
      $migration->addKey($table, 'otherserial');
      $migration->addKey($table, 'locations_id');
      $migration->addKey($table, 'states_id');
   }

   // Create tables :
   $tables = ['glpi_devicecasemodels',
              'glpi_devicecontrolmodels',
              'glpi_devicedrivemodels',
              'glpi_devicegraphiccardmodels',
              'glpi_deviceharddrivemodels',
              'glpi_devicememorymodels',
              'glpi_devicemotherboardmodels',
              'glpi_devicenetworkcardmodels',
              'glpi_devicepcimodels',
              'glpi_devicepowersupplymodels',
              'glpi_deviceprocessormodels',
              'glpi_devicesoundcardmodels',
              'glpi_devicegenericmodels',
              'glpi_devicebatterymodels',
              'glpi_devicefirmwaremodels',
              'glpi_devicesensormodels'];

   foreach ($tables as $table) {
      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE `$table` (
                      `id` INT(11) NOT NULL AUTO_INCREMENT,
                      `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                      `comment` TEXT NULL COLLATE 'utf8_unicode_ci',
                      `product_number` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                      PRIMARY KEY (`id`),
                      INDEX `name` (`name`),
                      INDEX `product_number` (`product_number`)
                   ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, "9.2 add model tables for devices");
      }
   }

   // Add a field in glpi_device* tables :
   $tables = ['glpi_devicecases'         => 'devicecasemodels_id',
              'glpi_devicecontrols'      => 'devicecontrolmodels_id',
              'glpi_devicedrives'        => 'devicedrivemodels_id',
              'glpi_devicegraphiccards'  => 'devicegraphiccardmodels_id',
              'glpi_deviceharddrives'    => 'deviceharddrivemodels_id',
              'glpi_devicememories'      => 'devicememorymodels_id',
              'glpi_devicemotherboards'  => 'devicemotherboardmodels_id',
              'glpi_devicenetworkcards'  => 'devicenetworkcardmodels_id',
              'glpi_devicepcis'          => 'devicepcimodels_id',
              'glpi_devicepowersupplies' => 'devicepowersupplymodels_id',
              'glpi_deviceprocessors'    => 'deviceprocessormodels_id',
              'glpi_devicesoundcards'    => 'devicesoundcardmodels_id'];

   foreach ($tables as $table => $field) {
      $migration->addField($table, $field, 'int', ['after' => 'is_recursive']);
      $migration->migrationOneTable($table);
      $migration->addKey($table, $field);
   }

   if (!$DB->tableExists('glpi_devicegenerics')) {
      $query = "CREATE TABLE `glpi_devicegenerics` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `designation` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `devicegenerictypes_id` int(11) NOT NULL DEFAULT '0',
                  `comment` text COLLATE utf8_unicode_ci,
                  `manufacturers_id` int(11) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `locations_id` int(11) NOT NULL DEFAULT '0',
                  `states_id` int(11) NOT NULL DEFAULT '0',
                  `devicegenericmodels_id` int(11) DEFAULT NULL,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `designation` (`designation`),
                  KEY `manufacturers_id` (`manufacturers_id`),
                  KEY `devicegenerictypes_id` (`devicegenerictypes_id`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `locations_id` (`locations_id`),
                  KEY `states_id` (`states_id`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`),
                  KEY `devicegenericmodels_id` (`devicegenericmodels_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, "9.2 add table glpi_devicegenerics");
   }

   if (!$DB->tableExists('glpi_items_devicegenerics')) {
      $query = "CREATE TABLE `glpi_items_devicegenerics` (
                   `id` INT(11) NOT NULL AUTO_INCREMENT,
                   `items_id` INT(11) NOT NULL DEFAULT '0',
                   `itemtype` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `devicegenerics_id` INT(11) NOT NULL DEFAULT '0',
                   `is_deleted` TINYINT(1) NOT NULL DEFAULT '0',
                   `is_dynamic` TINYINT(1) NOT NULL DEFAULT '0',
                   `entities_id` INT(11) NOT NULL DEFAULT '0',
                   `is_recursive` TINYINT(1) NOT NULL DEFAULT '0',
                   `serial` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `otherserial` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `locations_id` INT(11) NOT NULL DEFAULT '0',
                   `states_id` INT(11) NOT NULL DEFAULT '0',
                   PRIMARY KEY (`id`),
                   INDEX `computers_id` (`items_id`),
                   INDEX `devicegenerics_id` (`devicegenerics_id`),
                   INDEX `is_deleted` (`is_deleted`),
                   INDEX `is_dynamic` (`is_dynamic`),
                   INDEX `entities_id` (`entities_id`),
                   INDEX `is_recursive` (`is_recursive`),
                   INDEX `serial` (`serial`),
                   INDEX `item` (`itemtype`, `items_id`),
                   INDEX `otherserial` (`otherserial`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_items_devicegenerics");
   }

   if (!$DB->tableExists('glpi_devicegenerictypes')) {
      $query = "CREATE TABLE `glpi_devicegenerictypes` (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                  `comment` TEXT NULL COLLATE 'utf8_unicode_ci',
                   PRIMARY KEY (`id`),
                   INDEX `name` (`name`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_devicegenerictypes");
   }

   if (!$DB->tableExists('glpi_devicebatteries')) {
      $query = "CREATE TABLE `glpi_devicebatteries` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `designation` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `manufacturers_id` int(11) NOT NULL DEFAULT '0',
                  `voltage` int(11) DEFAULT NULL,
                  `capacity` int(11) DEFAULT NULL,
                  `devicebatterytypes_id` int(11) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `devicebatterymodels_id` int(11) DEFAULT NULL,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `designation` (`designation`),
                  KEY `manufacturers_id` (`manufacturers_id`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`),
                  KEY `devicebatterymodels_id` (`devicebatterymodels_id`),
                  KEY `devicebatterytypes_id` (`devicebatterytypes_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_devicebatteries");
   }

   if (!$DB->tableExists('glpi_items_devicebatteries')) {
      $query = "CREATE TABLE `glpi_items_devicebatteries` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `items_id` int(11) NOT NULL DEFAULT '0',
                  `itemtype` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `devicebatteries_id` int(11) NOT NULL DEFAULT '0',
                  `manufacturing_date` date DEFAULT NULL,
                  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                  `is_dynamic` tinyint(1) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `serial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `otherserial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `locations_id` int(11) NOT NULL DEFAULT '0',
                  `states_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `computers_id` (`items_id`),
                  KEY `devicebatteries_id` (`devicebatteries_id`),
                  KEY `is_deleted` (`is_deleted`),
                  KEY `is_dynamic` (`is_dynamic`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `serial` (`serial`),
                  KEY `item` (`itemtype`,`items_id`),
                  KEY `otherserial` (`otherserial`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_items_devicebatteries");
   }

   if (!$DB->tableExists('glpi_devicebatterytypes')) {
      $query = "CREATE TABLE `glpi_devicebatterytypes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_devicebatterytypes");
   }

   if (!$DB->tableExists('glpi_devicefirmwares')) {
      $query = "CREATE TABLE `glpi_devicefirmwares` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `designation` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `manufacturers_id` int(11) NOT NULL DEFAULT '0',
                  `date` date DEFAULT NULL,
                  `version` varchar(255) DEFAULT NULL,
                  `devicefirmwaretypes_id` int(11) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `devicefirmwaremodels_id` int(11) DEFAULT NULL,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `designation` (`designation`),
                  KEY `manufacturers_id` (`manufacturers_id`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`),
                  KEY `devicefirmwaremodels_id` (`devicefirmwaremodels_id`),
                  KEY `devicefirmwaretypes_id` (`devicefirmwaretypes_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_devicefirmwares");
   }
   if (!$DB->tableExists('glpi_items_devicefirmwares')) {
      $query = "CREATE TABLE `glpi_items_devicefirmwares` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `items_id` int(11) NOT NULL DEFAULT '0',
                  `itemtype` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `devicefirmwares_id` int(11) NOT NULL DEFAULT '0',
                  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                  `is_dynamic` tinyint(1) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `serial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `otherserial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `locations_id` int(11) NOT NULL DEFAULT '0',
                  `states_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `computers_id` (`items_id`),
                  KEY `devicefirmwares_id` (`devicefirmwares_id`),
                  KEY `is_deleted` (`is_deleted`),
                  KEY `is_dynamic` (`is_dynamic`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `serial` (`serial`),
                  KEY `item` (`itemtype`,`items_id`),
                  KEY `otherserial` (`otherserial`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_items_devicefirmwares");
   }
   if (!$DB->tableExists('glpi_devicefirmwaretypes')) {
      $query = "CREATE TABLE `glpi_devicefirmwaretypes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_devicefirmwaretypes");

      $DB->queryOrDie("INSERT INTO `glpi_devicefirmwaretypes` VALUES ('1','BIOS',NULL,NULL,NULL);");
      $DB->queryOrDie("INSERT INTO `glpi_devicefirmwaretypes` VALUES ('2','UEFI',NULL,NULL,NULL);");
      $DB->queryOrDie("INSERT INTO `glpi_devicefirmwaretypes` VALUES ('3','Firmware',NULL,NULL,NULL);");
   }

   //Device sensors
   if (!$DB->tableExists('glpi_devicesensors')) {
      $query = "CREATE TABLE `glpi_devicesensors` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `designation` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `devicesensortypes_id` int(11) NOT NULL DEFAULT '0',
                  `devicesensormodels_id` int(11) NOT NULL DEFAULT '0',
                  `comment` text COLLATE utf8_unicode_ci,
                  `manufacturers_id` int(11) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `locations_id` int(11) NOT NULL DEFAULT '0',
                  `states_id` int(11) NOT NULL DEFAULT '0',
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `designation` (`designation`),
                  KEY `manufacturers_id` (`manufacturers_id`),
                  KEY `devicesensortypes_id` (`devicesensortypes_id`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `locations_id` (`locations_id`),
                  KEY `states_id` (`states_id`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, "9.2 add table glpi_devicesensors");
   }

   if (!$DB->tableExists('glpi_items_devicesensors')) {
      $query = "CREATE TABLE `glpi_items_devicesensors` (
                   `id` INT(11) NOT NULL AUTO_INCREMENT,
                   `items_id` INT(11) NOT NULL DEFAULT '0',
                   `itemtype` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `devicesensors_id` INT(11) NOT NULL DEFAULT '0',
                   `is_deleted` TINYINT(1) NOT NULL DEFAULT '0',
                   `is_dynamic` TINYINT(1) NOT NULL DEFAULT '0',
                   `entities_id` INT(11) NOT NULL DEFAULT '0',
                   `is_recursive` TINYINT(1) NOT NULL DEFAULT '0',
                   `serial` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `otherserial` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                   `locations_id` INT(11) NOT NULL DEFAULT '0',
                   `states_id` INT(11) NOT NULL DEFAULT '0',
                   PRIMARY KEY (`id`),
                   INDEX `computers_id` (`items_id`),
                   INDEX `devicesensors_id` (`devicesensors_id`),
                   INDEX `is_deleted` (`is_deleted`),
                   INDEX `is_dynamic` (`is_dynamic`),
                   INDEX `entities_id` (`entities_id`),
                   INDEX `is_recursive` (`is_recursive`),
                   INDEX `serial` (`serial`),
                   INDEX `item` (`itemtype`, `items_id`),
                   INDEX `otherserial` (`otherserial`)
                )
                COLLATE='utf8_unicode_ci'
                ENGINE=MyISAM;";
      $DB->queryOrDie($query, "9.2 add table glpi_items_devicesensors");
   }

   if (!$DB->tableExists('glpi_devicesensortypes')) {
      $query = "CREATE TABLE `glpi_devicesensortypes` (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
                  `comment` TEXT NULL COLLATE 'utf8_unicode_ci',
                   PRIMARY KEY (`id`),
                   INDEX `name` (`name`)
                )
                COLLATE='utf8_unicode_ci' ENGINE=MyISAM;";
      $DB->queryOrDie($query, "9.2 add table glpi_devicesensortypes");
   }

   //Father/son for Software licenses
   $migration->addField("glpi_softwarelicenses", "softwarelicenses_id", "integer", ['after' => 'softwares_id']);
   $new = $migration->addField("glpi_softwarelicenses", "completename", "text", ['after' => 'softwarelicenses_id']);
   $migration->addField("glpi_softwarelicenses", "level", "integer", ['after' => 'completename']);
   $migration->migrationOneTable("glpi_softwarelicenses");
   if ($new) {
      $query = "UPDATE `glpi_softwarelicenses`
                SET `completename`=`name`";
      $DB->queryOrDie($query, "9.2 copy name to completename for software licenses");
   }

   // add template key to itiltasks
   $migration->addField("glpi_tickettasks", "tasktemplates_id", "integer");
   $migration->migrationOneTable('glpi_tickettasks');
   $migration->addKey("glpi_tickettasks", "tasktemplates_id");

   $migration->addField("glpi_problemtasks", "tasktemplates_id", "integer");
   $migration->migrationOneTable('glpi_problemtasks');
   $migration->addKey("glpi_problemtasks", "tasktemplates_id");

   $migration->addField("glpi_changetasks", "tasktemplates_id", "integer");
   $migration->migrationOneTable('glpi_changetasks');
   $migration->addKey("glpi_changetasks", "tasktemplates_id");

   // add missing fields to tasktemplate
   $migration->addField("glpi_tasktemplates", "state", "integer");
   $migration->addField("glpi_tasktemplates", "is_private", "bool");
   $migration->addField("glpi_tasktemplates", "users_id_tech", "integer");
   $migration->addField("glpi_tasktemplates", "groups_id_tech", "integer");
   $migration->migrationOneTable('glpi_tasktemplates');
   $migration->addKey("glpi_tickettasks", "is_private");
   $migration->addKey("glpi_tickettasks", "users_id_tech");
   $migration->addKey("glpi_tickettasks", "groups_id_tech");

   // #1735 - Add new notifications
   $notification       = new Notification;
   $notificationtarget = new NotificationTarget;
   $new_notifications  = [
      'requester_user'  => ['label'      => 'New user in requesters',
                            'targets_id' => Notification::AUTHOR],
      'requester_group' => ['label'      => 'New group in requesters',
                            'targets_id' => Notification::REQUESTER_GROUP],
      'observer_user'   => ['label'      => 'New user in observers',
                            'targets_id' => Notification::OBSERVER],
      'observer_group'  => ['label'      => 'New group in observers',
                            'targets_id' => Notification::OBSERVER_GROUP],
      'assign_user'     => ['label'      => 'New user in assignees',
                            'targets_id' => Notification::ASSIGN_TECH],
      'assign_group'    => ['label'      => 'New group in assignees',
                            'targets_id' => Notification::ITEM_TECH_GROUP_IN_CHARGE],
      'assign_supplier' => ['label'      => 'New supplier in assignees',
                            'targets_id' => Notification::SUPPLIER],
   ];

   if ($DB->fieldExists("glpi_notifications", "mode", false)) {
      foreach ($new_notifications as $event => $notif_options) {
         $notifications_id = $notification->add([
            'name'                     => $notif_options['label'],
            'itemtype'                 => 'Ticket',
            'event'                    => $event,
            'mode'                     => Notification_NotificationTemplate::MODE_MAIL,
            'notificationtemplates_id' => 0,
            'is_recursive'             => 1,
            'is_active'                => 0,
         ]);

         $notificationtarget->add([
            'items_id'         => $notif_options['targets_id'],
            'type'             => 1,
            'notifications_id' => $notifications_id,
         ]);
      }
   }

   $migration->addField('glpi_states', 'is_visible_certificate', 'bool', ['value' => 1]);
   $migration->addKey('glpi_states', 'is_visible_certificate');

   /** ************ New SLM structure ************ */
   if (!$DB->tableExists('glpi_olas')) {
      $query = "CREATE TABLE `glpi_olas` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `type` int(11) NOT NULL DEFAULT '0',
                  `comment` text COLLATE utf8_unicode_ci,
                  `number_time` int(11) NOT NULL,
                  `calendars_id` int(11) NOT NULL DEFAULT '0',
                  `date_mod` datetime DEFAULT NULL,
                  `definition_time` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `end_of_working_day` tinyint(1) NOT NULL DEFAULT '0',
                  `date_creation` datetime DEFAULT NULL,
                  `slms_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `calendars_id` (`calendars_id`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`),
                  KEY `slms_id` (`slms_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.2 add table glpi_olas");

      if (!$DB->tableExists('glpi_olalevelactions')) {
         $query = "CREATE TABLE `glpi_olalevelactions` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `olalevels_id` int(11) NOT NULL DEFAULT '0',
                 `action_type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `field` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 PRIMARY KEY (`id`),
                 KEY `olalevels_id` (`olalevels_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->queryOrDie($query, "9.2 add table glpi_olalevelactions");
      }

      if (!$DB->tableExists('glpi_olalevelcriterias')) {
         $query = "CREATE TABLE `glpi_olalevelcriterias` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `olalevels_id` int(11) NOT NULL DEFAULT '0',
                 `criteria` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `condition` int(11) NOT NULL DEFAULT '0' COMMENT 'see define.php PATTERN_* and REGEX_* constant',
                 `pattern` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 PRIMARY KEY (`id`),
                 KEY `olalevels_id` (`olalevels_id`),
                 KEY `condition` (`condition`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->queryOrDie($query, "9.2 add table glpi_olalevelcriterias");
      }

      if (!$DB->tableExists('glpi_olalevels')) {
         $query = "CREATE TABLE `glpi_olalevels` (
                 `id` int(11) NOT NULL AUTO_INCREMENT,
                 `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 `olas_id` int(11) NOT NULL DEFAULT '0',
                 `execution_time` int(11) NOT NULL,
                 `is_active` tinyint(1) NOT NULL DEFAULT '1',
                 `entities_id` int(11) NOT NULL DEFAULT '0',
                 `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                 `match` char(10) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'see define.php *_MATCHING constant',
                 `uuid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                 PRIMARY KEY (`id`),
                 KEY `name` (`name`),
                 KEY `is_active` (`is_active`),
                 KEY `olas_id` (`olas_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->queryOrDie($query, "9.2 add table glpi_olalevels");
      }

      if (!$DB->tableExists('glpi_olalevels_tickets')) {
         $query = "CREATE TABLE `glpi_olalevels_tickets` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tickets_id` int(11) NOT NULL DEFAULT '0',
                    `olalevels_id` int(11) NOT NULL DEFAULT '0',
                    `date` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `tickets_id` (`tickets_id`),
                    KEY `olalevels_id` (`olalevels_id`),
                    KEY `unicity` (`tickets_id`,`olalevels_id`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->queryOrDie($query, "9.2 add table glpi_olalevels_tickets");
      }

      $query = "INSERT INTO `glpi_crontasks`
                       (`itemtype`, `name`, `frequency`, `param`, `state`, `mode`, `allowmode`,
                        `hourmin`, `hourmax`, `logs_lifetime`, `lastrun`, `lastcode`, `comment`)
                VALUES ('OlaLevel_Ticket', 'olaticket', 604800, NULL, 0, 1, 3,
                        0, 24, 10, NULL, NULL, NULL); ";
      $DB->queryOrDie($query, "9.2 populate glpi_crontasks for olaticket");

      if (!$DB->tableExists('glpi_slms')) {
         // Changing the structure of the table 'glpi_slas'
         $migration->renameTable('glpi_slas', 'glpi_slms');
         $migration->migrationOneTable('glpi_slas');
      }

      // Changing the structure of the table 'glpi_slts'
      if ($DB->tableExists('glpi_slts')) {
         $migration->renameTable('glpi_slts', 'glpi_slas');
         $migration->migrationOneTable('glpi_slts');
         $migration->changeField('glpi_slas', 'slas_id', 'slms_id', 'integer');
         $migration->dropKey('glpi_slas', 'slas_id');
         $migration->addKey('glpi_slas', 'slms_id');
      }

      // Slalevels changes
      $migration->changeField('glpi_slalevels', 'slts_id', 'slas_id', 'integer');
      $migration->migrationOneTable('glpi_slalevels');
      $migration->dropKey('glpi_slalevels', 'slts_id');
      $migration->addKey('glpi_slalevels', 'slas_id');

      // Ticket changes
      $migration->changeField("glpi_tickets", "slts_ttr_id", "slas_ttr_id", "integer");
      $migration->changeField("glpi_tickets", "slts_tto_id", "slas_tto_id", "integer");
      $migration->changeField('glpi_tickets', 'due_date', 'time_to_resolve', 'datetime');
      $migration->addField("glpi_tickets", "ola_waiting_duration", "integer",
                           ['after' => 'sla_waiting_duration']);
      $migration->addField("glpi_tickets", "olas_tto_id", "integer", ['after' => 'ola_waiting_duration']);
      $migration->addField("glpi_tickets", "olas_ttr_id", "integer", ['after' => 'olas_tto_id']);
      $migration->addField("glpi_tickets", "ttr_olalevels_id", "integer", ['after' => 'olas_ttr_id']);
      $migration->addField("glpi_tickets", "internal_time_to_resolve", "datetime",
                           ['after' => 'ttr_olalevels_id']);
      $migration->addField("glpi_tickets", "internal_time_to_own", "datetime",
                           ['after' => 'internal_time_to_resolve']);
      $migration->migrationOneTable('glpi_tickets');
      $migration->dropKey('glpi_tickets', 'slts_tto_id');
      $migration->addKey('glpi_tickets', 'slas_tto_id');
      $migration->dropKey('glpi_tickets', 'slts_ttr_id');
      $migration->addKey('glpi_tickets', 'slas_ttr_id');
      $migration->dropKey('glpi_tickets', 'due_date');
      $migration->addKey('glpi_tickets', 'time_to_resolve');
      $migration->addKey('glpi_tickets', 'olas_tto_id');
      $migration->addKey('glpi_tickets', 'olas_ttr_id');
      $migration->addKey('glpi_tickets', 'internal_time_to_resolve');
      $migration->addKey('glpi_tickets', 'internal_time_to_own');

      //Change changes
      $migration->changeField('glpi_changes', 'due_date', 'time_to_resolve', 'datetime');
      $migration->migrationOneTable('glpi_changes');
      $migration->dropKey('glpi_changes', 'due_date');
      $migration->addKey('glpi_changes', 'time_to_resolve');

      //Problem changes
      $migration->changeField('glpi_problems', 'due_date', 'time_to_resolve', 'datetime');
      $migration->migrationOneTable('glpi_problems');
      $migration->dropKey('glpi_problems', 'due_date');
      $migration->addKey('glpi_problems', 'time_to_resolve');

      // ProfileRights changes
      $DB->queryOrDie("UPDATE `glpi_profilerights`
                       SET `name` = 'slm'
                       WHERE `name` = 'sla'",
                      "SLM profilerights migration");

       //Sla rules criterias migration
      $DB->queryOrDie("UPDATE `glpi_rulecriterias`
                       SET `criteria` = 'slas_ttr_id'
                       WHERE `criteria` = 'slts_ttr_id'",
                      "SLA rulecriterias migration");

      $DB->queryOrDie("UPDATE `glpi_rulecriterias`
                       SET `criteria` = 'slas_tto_id'
                       WHERE `criteria` = 'slts_tto_id'",
                      "SLA rulecriterias migration");

      // Sla rules actions migration
      $DB->queryOrDie("UPDATE `glpi_ruleactions`
                       SET `field` = 'slas_ttr_id'
                       WHERE `field` = 'slts_ttr_id'",
                      "SLA ruleactions migration");

      $DB->queryOrDie("UPDATE `glpi_ruleactions`
                       SET `field` = 'slas_tto_id'
                       WHERE `field` = 'slts_tto_id'",
                      "SLA ruleactions migration");
   }

   /************** Auto login **************/
   $migration->addConfig([
      'login_remember_time'      => 604800,
      'login_remember_default'   => 1
   ]);

   if ($DB->tableExists('glpi_bookmarks')) {
      $migration->renameTable("glpi_bookmarks", "glpi_savedsearches");

      $migration->addField("glpi_savedsearches", "last_execution_time", "int(11) NULL DEFAULT NULL");
      $migration->addField("glpi_savedsearches", "do_count",
                           "tinyint(1) NOT NULL DEFAULT '2' COMMENT 'Do or do not count results on list display; see SavedSearch::COUNT_* constants'");
      $migration->addField("glpi_savedsearches", "last_execution_date",
                           "DATETIME NULL DEFAULT NULL");
      $migration->addField("glpi_savedsearches", "counter", "int(11) NOT NULL DEFAULT '0'");
      $migration->migrationOneTable("glpi_savedsearches");
      $migration->addKey("glpi_savedsearches", 'last_execution_time');
      $migration->addKey("glpi_savedsearches", 'do_count');
      $migration->addKey("glpi_savedsearches", 'last_execution_date');

      //ensure do_count is set to AUTO
      $migration->addPostQuery("UPDATE `glpi_savedsearches`
                                SET `do_count` = " . SavedSearch::COUNT_AUTO);
      $migration->addPostQuery("UPDATE `glpi_savedsearches`
                                SET `entities_id` = 0
                                WHERE `entities_id` = -1");
   }

   if (!countElementsInTable('glpi_rules',
                             ['sub_type' => 'RuleSoftwareCategory',
                              'uuid'     => '500717c8-2bd6e957-53a12b5fd38869.86003425'])) {
      $rule = new Rule();
      $rules_id = $rule->add(['name'        => 'Import category from inventory tool',
                              'is_active'   => 0,
                              'uuid'        => '500717c8-2bd6e957-53a12b5fd38869.86003425',
                              'entities_id' => 0,
                              'sub_type'    => 'RuleSoftwareCategory',
                              'match'       => Rule::AND_MATCHING,
                              'condition'   => 0,
                              'description' => '']);
      if ($rules_id) {
         $criteria = new RuleCriteria();
         $criteria->add(['rules_id'  => $rules_id,
                         'criteria'  => 'name',
                         'condition' => '0',
                         'pattern'   => '*']);

         $action = new RuleAction();
         $action->add(['rules_id'    => $rules_id,
                       'action_type' => 'assign',
                       'field'       => '_import_category',
                       'value'       => '1']);
      }
   }

   if ($DB->tableExists('glpi_queuedmails')) {
      $migration->renameTable("glpi_queuedmails", "glpi_queuednotifications");
      $migration->addPostQuery("UPDATE `glpi_crontasks`
                                SET `itemtype` = 'QueuedNotification'
                                WHERE `itemtype` = 'QueuedMail'");
      $migration->addPostQuery("UPDATE `glpi_crontasks`
                                SET `name` = 'queuednotification'
                                WHERE `name` = 'queuedmail'");
      $migration->addPostQuery("UPDATE `glpi_crontasks`
                                SET `name` = 'queuednotificationclean'
                                WHERE `name` = 'queuedmailclean'");
      $migration->addPostQuery("UPDATE `glpi_profilerights`
                                SET `name` = 'queuednotification'
                                WHERE `name` = 'queuedmail'");
   }

   if (isset($current_config['use_mailing']) && !isset($current_config['use_notifications'])) {
      /** Notifications modes */
      $migration->addConfig([
         'use_notifications'                 => $current_config['use_mailing'],
                                      'notifications_mailing'    => $current_config['use_mailing'],
                                      'notifications_ajax'       => 0,
                                      'notifications_ajax_check_interval' => '5',
                                      'notifications_ajax_sound' => null,
         'notifications_ajax_icon_url'       => '/pics/glpi.png'
      ]);
   }

   if (!$DB->tableExists('glpi_notifications_notificationtemplates')) {
      $query = "CREATE TABLE `glpi_notifications_notificationtemplates` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `notifications_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `mode` varchar(20) COLLATE utf8_unicode_ci NOT NULL COMMENT 'See Notification_NotificationTemplate::MODE_* constants',
                  `notificationtemplates_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`notifications_id`, `mode`, `notificationtemplates_id`),
                  KEY `notifications_id` (`notifications_id`),
                  KEY `notificationtemplates_id` (`notificationtemplates_id`),
                  KEY `mode` (`mode`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_notifications_notificationtemplates");

      if ($DB->fieldExists("glpi_notifications", "mode", false)) {
         $query = "INSERT INTO `glpi_notifications_notificationtemplates`
                          (`notifications_id`, `mode`, `notificationtemplates_id`)
                          SELECT `id`, `mode`, `notificationtemplates_id`
                          FROM `glpi_notifications`";
         $DB->queryOrDie($query, "9.2 migrate notifications templates");

         //migrate any existing mode before removing the field
         $migration->dropField('glpi_notifications', 'mode');
         $migration->dropField('glpi_notifications', 'notificationtemplates_id');

         $migration->migrationOneTable("glpi_notifications");
      }
   }

   $migration->addField('glpi_queuednotifications', 'mode',
                        'varchar(20) COLLATE utf8_unicode_ci NOT NULL COMMENT \'See Notification_NotificationTemplate::MODE_* constants\'');
   $migration->migrationOneTable("glpi_queuednotifications");
   $migration->addKey('glpi_queuednotifications', 'mode');
   $migration->addPostQuery("UPDATE `glpi_queuednotifications`
                             SET `mode` = '" . Notification_NotificationTemplate::MODE_MAIL . "'",
                            '9.2 set default mode in queue');
   $migration->addPostQuery("UPDATE `glpi_notifications_notificationtemplates`
                             SET `mode` = '" . Notification_NotificationTemplate::MODE_MAIL . "'
                             WHERE `mode` = 'mail'",
                            '9.2 set default mode in notifications templates');

   // Migration Bookmark -> SavedSearch_Alert
   //TRANS: %s is the table or item to migrate
   if ($DB->tableExists('glpi_bookmarks_users')) {
      $migration->renameTable("glpi_bookmarks_users", "glpi_savedsearches_users");
      $migration->changeField('glpi_savedsearches_users', 'bookmarks_id', 'savedsearches_id',
                              'int(11) NOT NULL DEFAULT "0"');
   }

   if (!$DB->tableExists('glpi_savedsearches_alerts')) {
      $query = "CREATE TABLE `glpi_savedsearches_alerts` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `savedsearches_id` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `is_active` tinyint(1) NOT NULL DEFAULT '0',
                  `operator` tinyint(1) NOT NULL,
                  `value` int(11) NOT NULL,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `is_active` (`is_active`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`),
                  UNIQUE KEY `unicity` (`savedsearches_id`,`operator`, `value`)
                 ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_savedsearches_alerts");
   }

   $migration->displayMessage(sprintf(__('Data migration - %s'), 'glpi_displaypreferences'));

   $ADDTODISPLAYPREF['SavedSearch'] = [8, 9, 3, 10, 11];
   foreach ($ADDTODISPLAYPREF as $type => $tab) {
      $rank = 1;
      foreach ($tab as $newval) {
         $query = "REPLACE INTO `glpi_displaypreferences`
                           (`itemtype` ,`num` ,`rank` ,`users_id`)
                     VALUES ('$type', '$newval', '".$rank++."', '0')";
         $DB->query($query);
      }
   }

   if (countElementsInTable('glpi_logs') < 2000000) {
      //add index only if this sounds... possible.
      $migration->addKey("glpi_logs", "id_search_option");
   } else {
      //Just display a Warning to the user.
      $migration->displayWarning("An index must be added in the 'id_search_option' field " .
         "of the 'glpi_logs table'; but your gpi_logs table is " .
                                 "too huge. You'll have to add it on your database.");
   }

   // count cron task
   if (!countElementsInTable('glpi_crontasks',
                             "`itemtype`='SavedSearch' AND `name`='countAll'")) {
      $query = "INSERT INTO `glpi_crontasks`
                       (`itemtype`, `name`, `frequency`, `param`, `state`, `mode`, `allowmode`,
                        `hourmin`, `hourmax`, `logs_lifetime`, `lastrun`, `lastcode`, `comment`)
                VALUES ('SavedSearch', 'countAll', 604800, NULL, 0, 1, 3,
                        0, 24, 10, NULL, NULL, NULL); ";
      $DB->queryOrDie($query, "9.2 Add countAll SavedSearch cron task");
   }

   // alerts cron task
   if (!countElementsInTable('glpi_crontasks',
                             "`itemtype`='SavedSearch_Alert' AND `name`='savedsearchesalerts'")) {
       $query = "INSERT INTO `glpi_crontasks`
                       (`itemtype`, `name`, `frequency`, `param`, `state`, `mode`, `allowmode`,
                        `hourmin`, `hourmax`, `logs_lifetime`, `lastrun`, `lastcode`, `comment`)
                VALUES ('SavedSearch_Alert', 'savedsearchesalerts', 86400, NULL, 0, 1, 3,
                        0, 24, 10, NULL, NULL, NULL); ";
      $DB->queryOrDie($query, "9.2 Add saved searches alerts cron task");
   }

   if (!countElementsInTable('glpi_notifications',
                             "`itemtype`='SavedSearch_Alert'")) {
      $query = "INSERT INTO `glpi_notifications`
                (`id`, `name`, `entities_id`, `itemtype`, `event`, `comment`,
                 `is_recursive`, `is_active`, `date_creation`, `date_mod`)
                VALUES (null,'Saved searches','0','SavedSearch_Alert','alert',
                        '', '1', '1', NOW(), NOW());";
      $DB->queryOrDie($query, "9.2 Add saved search alerts notification");
      $notid = $DB->insert_id();

      $query = "INSERT INTO `glpi_notificationtemplates`
                     (`name`, `itemtype`, `date_mod`)
               VALUES ('Saved searches alerts', 'SavedSearch_Alert', NOW())";
      $DB->queryOrDie($query, "9.2 Add saved search alerts notification template");
      $nottid = $DB->insert_id();

      $where =  "`notifications_id`='$notid' AND `mode`='" .
         Notification_NotificationTemplate::MODE_MAIL.
         "' AND `notificationtemplates_id`='$nottid'";
      if (countElementsInTable('glpi_notifications_notificationtemplates', $where)) {
         $query = "INSERT INTO `glpi_notifications_notificationtemplates`
                   VALUES (null, $notid, '".Notification_NotificationTemplate::MODE_MAIL."', $nottid);";
         $DB->queryOrDie($query, "9.2 Add saved search alerts notification");
      }

      $query = "INSERT INTO `glpi_notificationtargets`
                VALUES (null,'19','1','$notid');";
      $DB->queryOrDie($query, "9.2 Add saved search alerts notification targets");

      $query = "INSERT INTO `glpi_notificationtemplatetranslations`
                       (`notificationtemplates_id`, `language`,`subject`,
                              `content_text`,
                              `content_html`)
                     VALUES ($notid, '', '##savedsearch.action## ##savedsearch.name##',
                     '##savedsearch.type## ###savedsearch.id## - ##savedsearch.name##

##savedsearch.message##

##lang.savedsearch.url##
##savedsearch.url##

Regards,',
                     '&lt;table&gt;
                     &lt;tbody&gt;
                     &lt;tr&gt;&lt;th colspan=\"2\"&gt;&lt;a href=\"##savedsearch.url##\"&gt;##savedsearch.type## ###savedsearch.id## - ##savedsearch.name##&lt;/a&gt;&lt;/th&gt;&lt;/tr&gt;
                     &lt;tr&gt;&lt;td colspan=\"2\"&gt;&lt;a href=\"##savedsearch.url##\"&gt;##savedsearch.message##&lt;/a&gt;&lt;/td&gt;&lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.savedsearch.url##&lt;/td&gt;
                     &lt;td&gt;##savedsearch.url##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;/tbody&gt;
                     &lt;/table&gt;
                     &lt;p&gt;&lt;span style=\"font-size: small;\"&gt;Hello &lt;br /&gt;Regards,&lt;/span&gt;&lt;/p&gt;')";

      $DB->queryOrDie($query, "9.2 add saved searches alerts notification translation");
   }

   // Create a dedicated token for api
   if (!$DB->fieldExists('glpi_users', 'api_token')) {
      $migration->addField('glpi_users', 'api_token', 'string', ['after' => 'personal_token_date']);
      $migration->addField('glpi_users', 'api_token_date', 'datetime', ['after' => 'api_token']);
      $migration->displayWarning("Api users tokens has been reset, if you use REST/XMLRPC api with personal token for authentication, please reset your user's token.",
                                 true);
   }

   if (!$DB->tableExists('glpi_items_operatingsystems')) {
      $query = "CREATE TABLE `glpi_items_operatingsystems` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `items_id` int(11) NOT NULL DEFAULT '0',
                  `itemtype` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `operatingsystems_id` int(11) NOT NULL DEFAULT '0',
                  `operatingsystemversions_id` int(11) NOT NULL DEFAULT '0',
                  `operatingsystemservicepacks_id` int(11) NOT NULL DEFAULT '0',
                  `operatingsystemarchitectures_id` int(11) NOT NULL DEFAULT '0',
                  `operatingsystemkernelversions_id` int(11) NOT NULL DEFAULT '0',
                  `license_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `license_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `operatingsystemeditions_id` int(11) NOT NULL DEFAULT '0',
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `items_id` (`items_id`),
                  KEY `item` (`itemtype`,`items_id`),
                  KEY `operatingsystems_id` (`operatingsystems_id`),
                  KEY `operatingsystemservicepacks_id` (`operatingsystemservicepacks_id`),
                  KEY `operatingsystemversions_id` (`operatingsystemversions_id`),
                  KEY `operatingsystemarchitectures_id` (`operatingsystemarchitectures_id`),
                  KEY `operatingsystemkernelversions_id` (`operatingsystemkernelversions_id`),
                  KEY `operatingsystemeditions_id` (`operatingsystemeditions_id`),
                  UNIQUE KEY `unicity`(`items_id`,`itemtype`, `operatingsystems_id`,
                                       `operatingsystemarchitectures_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_items_operatingsystems");
   }

   if (!$DB->tableExists('glpi_operatingsystemkernels')) {
      $query = "CREATE TABLE `glpi_operatingsystemkernels` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_operatingsystemkernels");
   }

   if (!$DB->tableExists('glpi_operatingsystemkernelversions')) {
      $query = "CREATE TABLE `glpi_operatingsystemkernelversions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `operatingsystemkernels_id` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `operatingsystemkernels_id` (`operatingsystemkernels_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_operatingsystemversions");
   }

   if (!$DB->tableExists('glpi_operatingsystemeditions')) {
      $query = "CREATE TABLE `glpi_operatingsystemeditions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_operatingsystemeditions");
   }

   if ($DB->fieldExists('glpi_computers', 'operatingsystems_id')) {
      //migrate data from computers table, and drop old fields
      $query = "INSERT INTO `glpi_items_operatingsystems`
                       (`itemtype`, `items_id`, `operatingsystems_id`, `operatingsystemversions_id`,
                        `operatingsystemservicepacks_id`, `operatingsystemarchitectures_id`,
                        `license_number`, `license_id`)
                       SELECT 'Computer', `id`, `operatingsystems_id`, `operatingsystemversions_id`,
                              `operatingsystemservicepacks_id`, `operatingsystemarchitectures_id`,
                              `os_license_number`, `os_licenseid`
                FROM `glpi_computers`
                WHERE `operatingsystems_id` != 0
                      OR `operatingsystemservicepacks_id` != 0
                      OR `operatingsystemarchitectures_id` != 0
                      OR `os_license_number` IS NOT NULL
                      OR `os_kernel_version` IS NOT NULL
                      OR `os_licenseid` IS NOT NULL";
      $DB->queryOrDie($query, "9.2 migrate main operating system informations");

      //migrate kernel versions.
      $kver = new OperatingSystemKernelVersion();
      $mapping = [];
      foreach ($DB->request(['SELECT' => ['id', 'os_kernel_version'],
                             'FROM'   => 'glpi_computers',
                             'NOT'   => ['os_kernel_version' => null]]) as $data) {
         $key = md5($data['os_kernel_version']);
         if (!isset($mapping[$key])) {
            $mapping[$key] = [];
         }
         $kver->add(['version' => $data['os_kernel_version']]);
         $mapping[$key][$data['id']] = $kver->getID();
      }

      foreach ($mapping as $map) {
         foreach ($map as $computers_id => $kver_id) {
            $query = "UPDATE glpi_items_operatingsystems
                      SET operatingsystemkernelversions_id = '$kver_id'
                      WHERE itemtype='Computer' AND items_id = '$computers_id'";
            $DB->queryOrDie($query);
         }
      }

      $migration->dropKey('glpi_computers', 'operatingsystems_id');
      $migration->dropField('glpi_computers', 'operatingsystems_id');
      $migration->dropKey('glpi_computers', 'operatingsystemservicepacks_id');
      $migration->dropField('glpi_computers', 'operatingsystemservicepacks_id');
      $migration->dropKey('glpi_computers', 'operatingsystemversions_id');
      $migration->dropField('glpi_computers', 'operatingsystemversions_id');
      $migration->dropKey('glpi_computers', 'operatingsystemarchitectures_id');
      $migration->dropField('glpi_computers', 'operatingsystemarchitectures_id');
      $migration->dropField('glpi_computers', 'os_license_number');
      $migration->dropField('glpi_computers', 'os_licenseid');
      $migration->dropField('glpi_computers', 'os_kernel_version');
   }

   //add db version
   $migration->addConfig(['dbversion' => GLPI_SCHEMA_VERSION]);

   // Add certificates management
   if (!$DB->tableExists('glpi_certificates')) {
      $query = "CREATE TABLE `glpi_certificates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) COLLATE utf8_unicode_ci  DEFAULT NULL,
        `serial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `otherserial` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `entities_id` INT(11) NOT NULL DEFAULT '0',
        `is_recursive` TINYINT(1) NOT NULL DEFAULT '0',
        `comment` text COLLATE utf8_unicode_ci,
        `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
        `is_template` tinyint(1) NOT NULL DEFAULT '0',
        `template_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `certificatetypes_id`  INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_certificatetypes (id)',
        `dns_name` VARCHAR(255) COLLATE utf8_unicode_ci  DEFAULT NULL,
        `dns_suffix` VARCHAR(255) COLLATE utf8_unicode_ci  DEFAULT NULL,
        `users_id_tech` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
        `groups_id_tech` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_groups (id)',
        `locations_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_locations (id)',
        `manufacturers_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_manufacturers (id)',
        `users_id` int(11) NOT NULL DEFAULT '0',
        `groups_id` int(11) NOT NULL DEFAULT '0',
        `is_autosign` TINYINT(1) NOT NULL DEFAULT '0',
        `date_expiration` DATE DEFAULT NULL,
        `states_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to states (id)',
        `command` TEXT COLLATE utf8_unicode_ci,
        `certificate_request` TEXT COLLATE utf8_unicode_ci,
        `certificate_item` TEXT COLLATE utf8_unicode_ci,
        `date_creation` DATETIME DEFAULT NULL,
        `date_mod` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `name` (`name`),
        KEY `entities_id` (`entities_id`),
        KEY `is_template` (`is_template`),
        KEY `is_deleted` (`is_deleted`),
        KEY `certificatetypes_id` (`certificatetypes_id`),
        KEY `users_id_tech` (`users_id_tech`),
        KEY `groups_id_tech` (`groups_id_tech`),
        KEY `groups_id` (`groups_id`),
        KEY `users_id` (`users_id`),
        KEY `locations_id` (`locations_id`),
        KEY `manufacturers_id` (`manufacturers_id`),
        KEY `states_id` (`states_id`),
        KEY `date_creation` (`date_creation`),
        KEY `date_mod` (`date_mod`)
      ) ENGINE = MyISAM DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 copy add certificate table");
   }

   if (!$DB->tableExists('glpi_certificates_items')) {
      $query = "CREATE TABLE `glpi_certificates_items` (
           `id` INT(11) NOT NULL AUTO_INCREMENT,
           `certificates_id` INT(11) NOT NULL DEFAULT '0',
           `items_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to various tables, according to itemtype (id)',
           `itemtype` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL COMMENT 'see .class.php file',
           `date_creation` DATETIME DEFAULT NULL,
           `date_mod` DATETIME DEFAULT NULL,
           PRIMARY KEY (`id`),
           UNIQUE KEY `unicity` (`certificates_id`, `itemtype`, `items_id`),
           KEY `device` (`items_id`, `itemtype`),
           KEY `item` (`itemtype`, `items_id`),
           KEY `date_creation` (`date_creation`),
           KEY `date_mod` (`date_mod`)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 copy add certificate items table");
   }

   if (!$DB->tableExists('glpi_certificatetypes')) {
      $query = "CREATE TABLE `glpi_certificatetypes` (
           `id` INT(11) NOT NULL AUTO_INCREMENT,
           `entities_id` INT(11) NOT NULL DEFAULT '0',
           `is_recursive` TINYINT(1) NOT NULL DEFAULT '0',
           `name` VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
           `comment` TEXT COLLATE utf8_unicode_ci,
           `date_creation` DATETIME DEFAULT NULL,
           `date_mod` DATETIME DEFAULT NULL,
           PRIMARY KEY (`id`),
           KEY `entities_id` (`entities_id`),
           KEY `is_recursive` (`is_recursive`),
           KEY `name` (`name`),
           KEY `date_creation` (`date_creation`),
           KEY `date_mod` (`date_mod`)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 copy add certificate type table");
   }

   if (countElementsInTable("glpi_profilerights", "`name` = 'certificate'") == 0) {
      //new right for certificate
      //give full rights to profiles having config right
      foreach ($DB->request("glpi_profilerights", "`name` = 'config'") as $profrights) {
         if ($profrights['rights'] && (READ + UPDATE)) {
            $rightValue = CREATE | READ | UPDATE | DELETE  | PURGE | READNOTE | UPDATENOTE | UNLOCK;
         } else {
            $rightValue = 0;
         }
         $query = "INSERT INTO `glpi_profilerights`
                          (`id`, `profiles_id`, `name`, `rights`)
                   VALUES (NULL, '".$profrights['profiles_id']."', 'certificate',
                           '".$rightValue."')";
         $DB->queryOrDie($query, "9.1 add right for certificates");
      }
   }

   // add alert for certificates
   $migration->addField("glpi_entities", 'use_certificates_alert', "integer",
                        ['value' => -2,
                         'after' => 'send_licenses_alert_before_delay']);
   $migration->addField("glpi_entities", 'send_certificates_alert_before_delay', "integer",
                        ['value'     => -2,
                         'after'     => 'use_certificates_alert',
                         'update'    => '0', // No delay for root entity
                         'condition' => 'WHERE `id` = 0']);
   CronTask::register(
      'Certificate',
      'certificate',
      DAY_TIMESTAMP,
      [
         'comment' => '',
         'mode'    => CronTask::MODE_INTERNAL
      ]
   );
   if (!countElementsInTable('glpi_notifications', "`itemtype`='Certificate'")) {
      $query = "INSERT INTO `glpi_notifications`
               (`id`, `name`, `entities_id`, `itemtype`, `event`, `comment`,
                `is_recursive`, `is_active`, `date_creation`, `date_mod`)
                VALUES (null,'Certificates','0','Certificate','alert',
                        '', '1', '1', NOW(), NOW());";
      $DB->queryOrDie($query, "9.2 Add certificate alerts notification");
      $notid = $DB->insert_id();

      $query = "INSERT INTO `glpi_notificationtemplates` (`name`, `itemtype`, `date_mod`)
                VALUES ('Certificates alerts', 'Certificate', NOW())";
      $DB->queryOrDie($query, "9.2 Add certifcate alerts notification template");
      $nottid = $DB->insert_id();

      $where =  "`notifications_id`='$notid' AND `mode`='" .
         Notification_NotificationTemplate::MODE_MAIL.
         "' AND `notificationtemplates_id`='$nottid'";
      if (!countElementsInTable('glpi_notifications_notificationtemplates', $where)) {
         $query = "INSERT INTO `glpi_notifications_notificationtemplates`
                   VALUES (null, $notid, '".Notification_NotificationTemplate::MODE_MAIL."', $nottid);";
         $DB->queryOrDie($query, "9.2 Add certificates alerts notification templates");
      }

      $query = "INSERT INTO `glpi_notificationtemplatetranslations`
                  (`notificationtemplates_id`, `language`, `subject`, `content_text`, `content_html`)
                VALUES ($notid, '', '##certificate.action##  ##certificate.entity##',
                        '##lang.certificate.entity## : ##certificate.entity##

##FOREACHcertificates##

##lang.certificate.serial## : ##certificate.serial##

##lang.certificate.expirationdate## : ##certificate.expirationdate##

##certificate.url##
 ##ENDFOREACHcertificates##','&lt;p&gt;
##lang.certificate.entity## : ##certificate.entity##&lt;br /&gt;
##FOREACHcertificates##
&lt;br /&gt;##lang.certificate.name## : ##certificate.name##&lt;br /&gt;
##lang.certificate.serial## : ##certificate.serial##&lt;br /&gt;
##lang.certificate.expirationdate## : ##certificate.expirationdate##
&lt;br /&gt; &lt;a href=\"##certificate.url##\"&gt; ##certificate.url##
&lt;/a&gt;&lt;br /&gt; ##ENDFOREACHcertificates##&lt;/p&gt;')";

      $DB->queryOrDie($query, "9.2 add certificates alerts notification translation");
   }

   /************** Simcard component **************/
   $migration->addField("glpi_states", "is_visible_line", "bool", ["after" => "is_visible_softwarelicense"]);

   if (!$DB->tableExists('glpi_lineoperators')) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_lineoperators` (
                   `id` int(11) NOT NULL AUTO_INCREMENT,
                   `name` varchar(255) NOT NULL DEFAULT '',
                   `comment` text COLLATE utf8_unicode_ci,
                   `mcc` int(11) DEFAULT NULL,
                   `mnc` int(11) DEFAULT NULL,
                   `entities_id`      INT(11) NOT NULL DEFAULT 0,
                   `is_recursive`     TINYINT(1) NOT NULL DEFAULT 0,
                   `date_mod` datetime DEFAULT NULL,
                   `date_creation` datetime DEFAULT NULL,
                   PRIMARY KEY (`id`),
                   KEY `name` (`name`),
                   KEY `entities_id`  (`entities_id`),
                   KEY `is_recursive` (`is_recursive`),
                   KEY `date_mod` (`date_mod`),
                   KEY `date_creation` (`date_creation`),
                   UNIQUE KEY `unicity` (`mcc`,`mnc`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.2 add table glpi_lineoperators");
   }

   if (!$DB->tableExists('glpi_linetypes')) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_linetypes` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         `comment` text COLLATE utf8_unicode_ci,
         `date_mod` datetime DEFAULT NULL,
         `date_creation` datetime DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `name` (`name`),
         KEY `date_mod` (`date_mod`),
         KEY `date_creation` (`date_creation`)
         ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.2 add table glpi_linetypes");
   }

   if (!$DB->tableExists('glpi_lines')) {
      $query = "CREATE TABLE `glpi_lines` (
            `id`                   INT(11) NOT NULL auto_increment,
            `name`                 VARCHAR(255) NOT NULL DEFAULT '',
            `entities_id`          INT(11) NOT NULL DEFAULT 0,
            `is_recursive`         TINYINT(1) NOT NULL DEFAULT 0,
            `is_deleted`           TINYINT(1) NOT NULL DEFAULT 0,
            `caller_num`           VARCHAR(255) NOT NULL DEFAULT '',
            `caller_name`          VARCHAR(255) NOT NULL DEFAULT '',
            `users_id`             INT(11) NOT NULL DEFAULT 0,
            `groups_id`            INT(11) NOT NULL DEFAULT 0,
            `lineoperators_id`     INT(11) NOT NULL DEFAULT 0,
            `locations_id`         INT(11) NOT NULL DEFAULT '0',
            `states_id`            INT(11) NOT NULL DEFAULT '0',
            `linetypes_id`         INT(11) NOT NULL DEFAULT '0',
            `date_creation`        DATETIME DEFAULT NULL,
            `date_mod`             DATETIME DEFAULT NULL,
            `comment`              TEXT DEFAULT NULL,
            PRIMARY KEY            (`id`),
            KEY `entities_id`      (`entities_id`),
            KEY `is_recursive`     (`is_recursive`),
            KEY `users_id`         (`users_id`),
            KEY `lineoperators_id` (`lineoperators_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_lines");
   }

   if (!$DB->tableExists('glpi_devicesimcardtypes')) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_devicesimcardtypes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL DEFAULT '',
                  `comment` text COLLATE utf8_unicode_ci,
                  `date_mod` datetime DEFAULT NULL,
                  `date_creation` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.2 add table glpi_devicesimcardtypes");
   }

   if (!countElementsInTable('glpi_devicesimcardtypes', "`name`='Full SIM'")) {
      $DB->queryOrDie("INSERT INTO `glpi_devicesimcardtypes` VALUES (NULL,'Full SIM',NULL,NULL,NULL)");
   }
   if (!countElementsInTable('glpi_devicesimcardtypes', "`name`='Mini SIM'")) {
      $DB->queryOrDie("INSERT INTO `glpi_devicesimcardtypes` VALUES (NULL,'Mini SIM',NULL,NULL,NULL)");
   }
   if (!countElementsInTable('glpi_devicesimcardtypes', "`name`='Micro SIM'")) {
      $DB->queryOrDie("INSERT INTO `glpi_devicesimcardtypes` VALUES (NULL,'Micro SIM',NULL,NULL,NULL)");
   }
   if (!countElementsInTable('glpi_devicesimcardtypes', "`name`='Nano SIM'")) {
      $DB->queryOrDie("INSERT INTO `glpi_devicesimcardtypes` VALUES (NULL,'Nano SIM',NULL,NULL,NULL)");
   }

   if (!$DB->tableExists('glpi_devicesimcards')) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_devicesimcards` (
               `id` int(11) NOT NULL AUTO_INCREMENT,
               `designation` varchar(255) DEFAULT NULL,
               `comment` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
               `entities_id` int(11) NOT NULL DEFAULT '0',
               `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
               `manufacturers_id` int(11) NOT NULL DEFAULT '0',
               `voltage` int(11) DEFAULT NULL,
               `devicesimcardtypes_id` int(11) NOT NULL DEFAULT '0',
               `date_mod` datetime DEFAULT NULL,
               `date_creation` datetime DEFAULT NULL,
               `allow_voip` tinyint(1) NOT NULL DEFAULT '0',
               PRIMARY KEY (`id`),
               KEY `designation` (`designation`),
               KEY `entities_id` (`entities_id`),
               KEY `is_recursive` (`is_recursive`),
               KEY `devicesimcardtypes_id` (`devicesimcardtypes_id`),
               KEY `date_mod` (`date_mod`),
               KEY `date_creation` (`date_creation`),
               KEY `manufacturers_id` (`manufacturers_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->queryOrDie($query, "9.2 add table glpi_devicesimcards");
   }

   if (!$DB->tableExists('glpi_items_devicesimcards')) {
      $query = "CREATE TABLE IF NOT EXISTS `glpi_items_devicesimcards` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `items_id` int(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to various table, according to itemtype (id)',
                  `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
                  `devicesimcards_id` int(11) NOT NULL DEFAULT '0',
                  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                  `is_dynamic` tinyint(1) NOT NULL DEFAULT '0',
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `serial` varchar(255) NULL DEFAULT NULL,
                  `otherserial` varchar(255) NULL DEFAULT NULL,
                  `states_id` int(11) NOT NULL DEFAULT '0',
                  `locations_id` int(11) NOT NULL DEFAULT '0',
                  `lines_id` int(11) NOT NULL DEFAULT '0',
                  `pin` varchar(255) NOT NULL DEFAULT '',
                  `pin2` varchar(255) NOT NULL DEFAULT '',
                  `puk` varchar(255) NOT NULL DEFAULT '',
                  `puk2` varchar(255) NOT NULL DEFAULT '',
                  PRIMARY KEY (`id`),
                  KEY `item` (`itemtype`,`items_id`),
                  KEY `devicesimcards_id` (`devicesimcards_id`),
                  KEY `is_deleted` (`is_deleted`),
                  KEY `is_dynamic` (`is_dynamic`),
                  KEY `entities_id` (`entities_id`),
                  KEY `serial` (`serial`),
                  KEY `otherserial` (`otherserial`),
                  KEY `states_id` (`states_id`),
                  KEY `locations_id` (`locations_id`),
                  KEY `lines_id` (`lines_id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.2 add table glpi_items_devicesimcards");
   }

   if (countElementsInTable("glpi_profilerights", "`name` = 'line'") == 0) {
      //new right for line
      //give full rights to profiles having config right
      foreach ($DB->request("glpi_profilerights", "`name` = 'config'") as $profrights) {
         if ($profrights['rights'] && (READ + UPDATE)) {
            $rightValue = CREATE | READ | UPDATE | DELETE | PURGE | READNOTE | UPDATENOTE;
         } else {
            $rightValue = 0;
         }
         $query = "INSERT INTO `glpi_profilerights`
                          (`id`, `profiles_id`, `name`, `rights`)
                   VALUES (NULL, '".$profrights['profiles_id']."', 'line',
                           '".$rightValue."')";
         $DB->queryOrDie($query, "9.1 add right for line");
      }
   }

   if (countElementsInTable("glpi_profilerights", "`name` = 'lineoperator'") == 0) {
      //new right for lineoperator
      //give full rights to profiles having config right
      foreach ($DB->request("glpi_profilerights", "`name` = 'config'") as $profrights) {
         if ($profrights['rights'] && (READ + UPDATE)) {
            $rightValue = CREATE | READ | UPDATE | DELETE | PURGE;
         } else {
            $rightValue = 0;
         }
         $query = "INSERT INTO `glpi_profilerights`
                          (`id`, `profiles_id`, `name`, `rights`)
                   VALUES (NULL, '".$profrights['profiles_id']."', 'lineoperator',
                           '".$rightValue."')";
         $DB->queryOrDie($query, "9.1 add right for lineoperator");
      }
   }

   if (countElementsInTable("glpi_profilerights", "`name` = 'devicesimcard_pinpuk'") == 0) {
      //new right for simcard pin and puk
      //give full rights to profiles having config right
      foreach ($DB->request("glpi_profilerights", "`name` = 'config'") as $profrights) {
         if ($profrights['rights'] && (READ + UPDATE)) {
            $rightValue = READ | UPDATE;
         } else {
            $rightValue = 0;
         }
         $query = "INSERT INTO `glpi_profilerights`
                          (`id`, `profiles_id`, `name`, `rights`)
                   VALUES (NULL, '".$profrights['profiles_id']."', 'devicesimcard_pinpuk',
                           '".$rightValue."')";
         $DB->queryOrDie($query, "9.1 add right for simcards pin and puk codes");
      }
   }

   //Firmware for phones
   if ($DB->fieldExists('glpi_phones', 'firmware')) {
      $iterator = $DB->request([
         'SELECT' => ['id', 'firmware'],
         'FROM'   => 'glpi_phones',
         'NOT'    => ['firmware' => null]
      ]);

      $firmwares = [];
      while ($row = $iterator->next()) {
         if (!isset($firmwares[$row['firmware']])) {
            $fw = new DeviceFirmware();
            if ($fw->getFromDBByCrit(['designation' => $row['firmware']])) {
               $firmwares[$row['firmware']] = $fw->getID();
            } else {
               $id = $fw->add([
                  'designation'              => $row['firmware'],
                  'devicefirmwaretypes_id'   => '3' //type "firmware"
               ]);
               $firmwares[$row['firmware']] = $id;
            }
         }

         //add link
         $item_fw = new Item_DeviceFirmware();
         $item_fw->add([
            'itemtype'           => 'Phone',
            'items_id'           => $row['id'],
            'devicefirmwares_id' => $firmwares[$row['firmware']]
         ]);
      }

      $migration->dropField('glpi_phones', 'firmware');
   }

   //Firmware for network equipements
   if ($DB->tableExists('glpi_networkequipmentfirmwares')) {
      $mapping = [];
      $iterator = $DB->request('glpi_networkequipmentfirmwares');
      while ($row = $iterator->next()) {
         $fw = new DeviceFirmware();
         $id = $fw->add([
            'designation'              => $row['name'],
            'comment'                  => $row['comment'],
            'devicefirmwaretypes_id'   => 3, //type "Firmware"
            'date_creation'            => $row['date_creation'],
            'date_mod'                 => $row['date_mod']
         ]);
         $mapping[$row['id']] = $id;
      }

      $iterator = $DB->request('glpi_networkequipments');
      while ($row = $iterator->next()) {
         if (isset($mapping[$row['networkequipmentfirmwares_id']])) {
            $itemdevice = new Item_DeviceFirmware();
            $itemdevice->add([
               'itemtype'           => 'NetworkEquipment',
               'items_id'           => $row['id'],
               'devicefirmwares_id' => $mapping[$row['networkequipmentfirmwares_id']]
            ]);
         }
      }

      $migration->dropKey('glpi_networkequipments', 'networkequipmentfirmwares_id');
      $migration->dropField('glpi_networkequipments', 'networkequipmentfirmwares_id');
      $migration->dropTable('glpi_networkequipmentfirmwares');
   }

   // add projecttemplate
   if (!$DB->fieldExists('glpi_projects', 'projecttemplates_id')) {
      $migration->addField("glpi_projects", "projecttemplates_id", "integer");
      $migration->addField("glpi_projects", "is_template", "bool");
      $migration->addField("glpi_projects", "template_name", "string");
      $migration->addKey("glpi_projects", "projecttemplates_id");
   }

   if (!$DB->fieldExists('glpi_projecttasks', 'projecttasktemplates_id')) {
      $migration->addField("glpi_projecttasks", "projecttasktemplates_id", "integer");
      $migration->addField("glpi_projecttasks", "is_template", "bool");
      $migration->addField("glpi_projecttasks", "template_name", "string");
      $migration->addKey("glpi_projecttasks", "projecttasktemplates_id");
   }

   if (!$DB->tableExists('glpi_projecttasktemplates')) {
      $query = "CREATE TABLE `glpi_projecttasktemplates` (
                       `id` int(11) NOT NULL AUTO_INCREMENT,
                       `entities_id` int(11) NOT NULL DEFAULT '0',
                       `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                       `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                       `description` longtext COLLATE utf8_unicode_ci,
                       `comment` longtext COLLATE utf8_unicode_ci,
                       `projects_id` int(11) NOT NULL DEFAULT '0',
                       `projecttasks_id` int(11) NOT NULL DEFAULT '0',
                       `plan_start_date` datetime DEFAULT NULL,
                       `plan_end_date` datetime DEFAULT NULL,
                       `real_start_date` datetime DEFAULT NULL,
                       `real_end_date` datetime DEFAULT NULL,
                       `planned_duration` int(11) NOT NULL DEFAULT '0',
                       `effective_duration` int(11) NOT NULL DEFAULT '0',
                       `projectstates_id` int(11) NOT NULL DEFAULT '0',
                       `projecttasktypes_id` int(11) NOT NULL DEFAULT '0',
                       `users_id` int(11) NOT NULL DEFAULT '0',
                       `percent_done` int(11) NOT NULL DEFAULT '0',
                       `is_milestone` tinyint(1) NOT NULL DEFAULT '0',
                       `comments` text COLLATE utf8_unicode_ci,
                       `date_mod` datetime DEFAULT NULL,
                       `date_creation` datetime DEFAULT NULL,
                       PRIMARY KEY (`id`),
                       KEY `name` (`name`),
                       KEY `entities_id` (`entities_id`),
                       KEY `is_recursive` (`is_recursive`),
                       KEY `projects_id` (`projects_id`),
                       KEY `projecttasks_id` (`projecttasks_id`),
                       KEY `date_creation` (`date_creation`),
                       KEY `date_mod` (`date_mod`),
                       KEY `users_id` (`users_id`),
                       KEY `plan_start_date` (`plan_start_date`),
                       KEY `plan_end_date` (`plan_end_date`),
                       KEY `real_start_date` (`real_start_date`),
                       KEY `real_end_date` (`real_end_date`),
                       KEY `percent_done` (`percent_done`),
                       KEY `projectstates_id` (`projectstates_id`),
                       KEY `projecttasktypes_id` (`projecttasktypes_id`),
                       KEY `is_milestone` (`is_milestone`)
                     ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.2 add table glpi_projecttasktemplates");
   }

   //add editor in followupps
   if (!$DB->fieldExists('glpi_ticketfollowups', 'users_id_editor')) {
      $migration->addField("glpi_ticketfollowups", "users_id_editor", "int(11) NOT NULL DEFAULT '0'", ['after' => 'users_id']);
      $migration->addKey("glpi_ticketfollowups", "users_id_editor");
   }

   //add editor in *tasks
   if (!$DB->fieldExists('glpi_tickettasks', 'users_id_editor')) {
      $migration->addField("glpi_tickettasks", "users_id_editor", "int(11) NOT NULL DEFAULT '0'", ['after' => 'users_id']);
      $migration->addKey("glpi_tickettasks", "users_id_editor");
   }
   if (!$DB->fieldExists('glpi_changetasks', 'users_id_editor')) {
      $migration->addField("glpi_changetasks", "users_id_editor", "int(11) NOT NULL DEFAULT '0'", ['after' => 'users_id']);
      $migration->addKey("glpi_changetasks", "users_id_editor");
   }
   if (!$DB->fieldExists('glpi_problemtasks', 'users_id_editor')) {
      $migration->addField("glpi_problemtasks", "users_id_editor", "int(11) NOT NULL DEFAULT '0'", ['after' => 'users_id']);
      $migration->addKey("glpi_problemtasks", "users_id_editor");
   }

   $migration->addConfig([
      'smtp_max_retries'   => 5,
      'smtp_sender'        => 'NULL',
      'from_email'         => 'NULL',
      'from_email_name'    => 'NULL'
   ]);

   //register telemetry crontask
   CronTask::register(
      'Telemetry',
      'telemetry',
      MONTH_TIMESTAMP,
      [
         'comment'   => '',
         'mode'      => CronTask::MODE_INTERNAL,
         'state'     => CronTask::STATE_DISABLE
      ]
   );
   $migration->addConfig([
      'instance_uuid'      => Telemetry::generateInstanceUuid(),
      'registration_uuid'  => Telemetry::generateRegistrationUuid()
   ]);

   if (isIndex('glpi_authldaps', 'use_tls')) {
      $query = "ALTER TABLE `glpi_authldaps` DROP INDEX `use_tls`";
      $DB->queryOrDie($query, "9.2 drop index use_tls for glpi_authldaps");
   }

   //Fix some field order from old migrations
   $migration->migrationOneTable('glpi_states');
   $DB->queryOrDie("ALTER TABLE `glpi_budgets` CHANGE `date_creation` `date_creation` DATETIME NULL DEFAULT NULL AFTER `date_mod`");
   $DB->queryOrDie("ALTER TABLE `glpi_changetasks` CHANGE `groups_id_tech` `groups_id_tech` INT(11) NOT NULL DEFAULT '0' AFTER `users_id_tech`");
   $DB->queryOrDie("ALTER TABLE `glpi_problemtasks` CHANGE `groups_id_tech` `groups_id_tech` INT(11) NOT NULL DEFAULT '0' AFTER `users_id_tech`");
   $DB->queryOrDie("ALTER TABLE `glpi_tickettasks` CHANGE `groups_id_tech` `groups_id_tech` INT(11) NOT NULL DEFAULT '0' AFTER `users_id_tech`");
   $DB->queryOrDie("ALTER TABLE `glpi_knowbaseitemcategories` CHANGE `sons_cache` `sons_cache` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `level`");
   $DB->queryOrDie("ALTER TABLE `glpi_requesttypes` CHANGE `is_followup_default` `is_followup_default` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_helpdesk_default`");
   $DB->queryOrDie("ALTER TABLE `glpi_requesttypes` CHANGE `is_mailfollowup_default` `is_mailfollowup_default` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_mail_default`");
   $DB->queryOrDie("ALTER TABLE `glpi_requesttypes` CHANGE `comment` `comment` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `is_ticketfollowup`");
   $DB->queryOrDie("ALTER TABLE `glpi_requesttypes` CHANGE `date_mod` `date_mod` DATETIME NULL DEFAULT NULL AFTER `comment`");
   $DB->queryOrDie("ALTER TABLE `glpi_requesttypes` CHANGE `date_creation` `date_creation` DATETIME NULL DEFAULT NULL AFTER `date_mod`");
   $DB->queryOrDie("ALTER TABLE `glpi_groups` CHANGE `is_task` `is_task` TINYINT(1) NOT NULL DEFAULT '1' AFTER `is_assign`");
   $DB->queryOrDie("ALTER TABLE `glpi_states` CHANGE `date_mod` `date_mod` DATETIME NULL DEFAULT NULL AFTER `is_visible_certificate`");
   $DB->queryOrDie("ALTER TABLE `glpi_states` CHANGE `date_creation` `date_creation` DATETIME NULL DEFAULT NULL AFTER `date_mod`");
   $DB->queryOrDie("ALTER TABLE `glpi_taskcategories` CHANGE `is_active` `is_active` TINYINT(1) NOT NULL DEFAULT '1' AFTER `sons_cache`");
   $DB->queryOrDie("ALTER TABLE `glpi_users` CHANGE `palette` `palette` CHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `layout`");
   $DB->queryOrDie("ALTER TABLE `glpi_users` CHANGE `set_default_requester` `set_default_requester` TINYINT(1) NULL DEFAULT NULL AFTER `ticket_timeline_keep_replaced_tabs`");
   $DB->queryOrDie("ALTER TABLE `glpi_users` CHANGE `plannings` `plannings` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `highcontrast_css`");

   //Fix bad default values
   $DB->queryOrDie("ALTER TABLE `glpi_states` CHANGE `is_visible_softwarelicense` `is_visible_softwarelicense` TINYINT(1) NOT NULL DEFAULT '1'");
   $DB->queryOrDie("ALTER TABLE `glpi_states` CHANGE `is_visible_line` `is_visible_line` TINYINT(1) NOT NULL DEFAULT '1'");

   //Fields added in 0905_91 script but not in empty sql...
   if (!$DB->fieldExists('glpi_changetasks', 'date_creation', false)) {
      $migration->addField('glpi_changetasks', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_changetasks', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_networkportfiberchannels', 'date_mod', false)) {
      $migration->addField('glpi_networkportfiberchannels', 'date_mod', 'datetime', ['after' => 'speed']);
      $migration->addKey('glpi_networkportfiberchannels', 'date_mod');
   }
   if (!$DB->fieldExists('glpi_networkportfiberchannels', 'date_creation', false)) {
      $migration->addField('glpi_networkportfiberchannels', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_networkportfiberchannels', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_problemtasks', 'date_creation', false)) {
      $migration->addField('glpi_problemtasks', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_problemtasks', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_slms', 'date_creation', false)) {
      $migration->addField('glpi_slms', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_slms', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_ticketfollowups', 'date_creation', false)) {
      $migration->addField('glpi_ticketfollowups', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_ticketfollowups', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_tickettasks', 'date_creation', false)) {
      $migration->addField('glpi_tickettasks', 'date_creation', 'datetime', ['after' => 'date_mod']);
      $migration->addKey('glpi_tickettasks', 'date_creation');
   }
   if (!$DB->fieldExists('glpi_softwarelicenses', 'contact_num', false)) {
      $migration->addField(
         "glpi_softwarelicenses",
         "contact_num",
         "varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL",
         ['after' => 'contact']
      );
   }

   //Fix comments...
   $DB->queryOrDie("ALTER TABLE `glpi_savedsearches` CHANGE `type` `type` INT(11) NOT NULL DEFAULT '0' COMMENT 'see SavedSearch:: constants'");

   //Fix unicity...
   $tables = [
      'glpi_slalevels_tickets'   => ['tickets_id', 'slalevels_id'],
      'glpi_businesscriticities' => ['businesscriticities_id', 'name'],
      'glpi_documentcategories'  => ['documentcategories_id', 'name'],
      'glpi_olalevels_tickets'   => ['tickets_id', 'olalevels_id'],
      'glpi_states'              => ['states_id', 'name'],
      'glpi_tickets_tickets'     => ['tickets_id_1', 'tickets_id_2'],
      'glpi_tickettemplatehiddenfields'      => ['tickettemplates_id', 'num'],
      'glpi_tickettemplatemandatoryfields'   => ['tickettemplates_id', 'num'],
      'glpi_tickettemplatepredefinedfields'  => ['tickettemplates_id', 'num']
   ];
   foreach ($tables as $table => $fields) {
      $add = true;
      $result = $DB->query("SHOW INDEX FROM `$table` WHERE Key_name='unicity'");
      if ($result && $DB->numrows($result)) {
         $row = $result->fetch_assoc();
         if ($row['Non_unique'] == 1) {
            $migration->dropKey($table, 'unicity');
            $migration->migrationOneTable($table);
         } else {
            $add = false;
         }
      }

      if ($add) {
         //missing or bad unique key ==> add it.
         $migration->addKey(
            $table,
            $fields,
            'unicity',
            'UNIQUE'
         );

      }
   }

   //removed field
   if ($DB->fieldExists('glpi_slms', 'resolution_time')) {
      $migration->dropField('glpi_slms', 'resolution_time');
   }

   //wrong type
   $DB->queryOrDie("ALTER TABLE `glpi_users` CHANGE `keep_devices_when_purging_item` `keep_devices_when_purging_item` TINYINT(1) NULL DEFAULT NULL");

   //missing index
   $migration->addKey('glpi_networknames', 'is_deleted');
   $migration->addKey('glpi_networknames', 'is_dynamic');
   $migration->addKey('glpi_projects', 'is_template');
   $migration->addKey('glpi_projecttasks', 'is_template');
   $migration->dropKey('glpi_savedsearches_users', 'bookmarks_id');
   $migration->addKey('glpi_savedsearches_users', 'savedsearches_id');
   //this one was not on the correct field
   $migration->dropKey('glpi_softwarelicenses', 'is_deleted');
   $migration->migrationOneTable('glpi_softwarelicenses');
   $migration->addKey('glpi_softwarelicenses', 'is_deleted');
   $migration->addKey('glpi_states', 'is_visible_line');
   $migration->addKey('glpi_tasktemplates', 'is_private');
   $migration->addKey('glpi_tasktemplates', 'users_id_tech');
   $migration->addKey('glpi_tasktemplates', 'groups_id_tech');

   // ************ Keep it at the end **************
   $migration->executeMigration();

   return $updateresult;
}
