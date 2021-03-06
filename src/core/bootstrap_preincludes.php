<?php
/**
 * Core bootstrap helper file that includes all the necessary core files
 *
 * This file is the core of the application; it's responsible for setting up
 *  all the necessary paths, settings and includes.
 *
 * @package Core
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2012  Charlie Powell
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */

// These are generally files required for getting the rest of the system loadable.
require_once(ROOT_PDIR . "core/libs/core/ISingleton.interface.php");
//require_once("core/classes/IDatabaseClass.interface.php");
require_once(ROOT_PDIR . 'core/libs/core/XMLLoader.class.php');
//require_once(ROOT_PDIR . 'core/libs/core/JSLibrary.class.php');
//require_once(ROOT_PDIR . 'core/libs/core/SQLBuilder.class.php');
/** @deprecated 2011.11 */
require_once(ROOT_PDIR . 'core/libs/core/InstallArchive.class.php');
/** @deprecated 2011.11 */
require_once(ROOT_PDIR . 'core/libs/core/InstallArchiveAPI.class.php');


// The PHP elements of the MVC framework.
require_once(ROOT_PDIR . 'core/libs/core/Model.class.php');
require_once(ROOT_PDIR . 'core/libs/core/Controller.class.php');

// Time is a useful component.
require_once(ROOT_PDIR . 'core/libs/core/Time.class.php');

require_once(ROOT_PDIR . 'core/models/ComponentModel.class.php');
require_once(ROOT_PDIR . 'core/models/PageModel.class.php');
require_once(ROOT_PDIR . 'core/models/SessionModel.class.php');
require_once(ROOT_PDIR . 'core/models/PageMetaModel.class.php');
require_once(ROOT_PDIR . 'core/models/Insertable.class.php');

/** @deprecated 2011.11 */
require_once(ROOT_PDIR . 'core/libs/core/Component.class.php');
/**
 * The Component system written for API 2.1
 */
require_once(ROOT_PDIR . 'core/libs/core/Component_2_1.php');
require_once(ROOT_PDIR . 'core/functions/Core.functions.php');

// File manipulation is a core feature required by the component system.
require_once(ROOT_PDIR . 'core/libs/core/filestore/functions.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/File.interface.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/Directory.interface.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/Factory.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/Directory_Backend.interface.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/FileContentFactory.class.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/Contents.interface.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/contents/ContentXML.php');
//require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/file_awss3.backend.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/FileLocal.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/FileFTP.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/FileRemote.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/DirectoryLocal.php');
require_once(ROOT_PDIR . 'core/libs/core/filestore/backends/DirectoryFTP.php');

// Many of these are needed because some systems, such as the installer
// execute before the ComponentHandler has loaded the class locations.
require_once(ROOT_PDIR . 'core/libs/core/ComponentFactory.php');
require_once(ROOT_PDIR . 'core/libs/core/ComponentHandler.class.php');
require_once(ROOT_PDIR . 'core/libs/cachecore/backends/icachecore.interface.php');
require_once(ROOT_PDIR . 'core/libs/cachecore/backends/cachecore.class.php');
require_once(ROOT_PDIR . 'core/libs/cachecore/backends/cachefile.class.php');
require_once(ROOT_PDIR . 'core/libs/cachecore/Cache.class.php');
require_once(ROOT_PDIR . 'core/libs/core/ViewControl.class.php');
require_once(ROOT_PDIR . 'core/libs/core/ViewMeta.class.php');

