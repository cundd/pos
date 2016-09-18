<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 09.10.14
 * Time: 14:18
 */

namespace Cundd\PersistentObjectStore;

/**
 * Collections of constants used through out the system
 *
 * @package Cundd\PersistentObjectStore
 */
interface Constants
{
    /**
     * JSON welcome message
     */
    const MESSAGE_JSON_WELCOME = '♜ - STAIRTOWER - PERSISTENT OBJECT STORE';

    /**
     * CLI welcome message
     */
    const MESSAGE_CLI_WELCOME = <<<WELCOME


                        /\
                       /  \
                      /____\
      __________      |    |
    /__________/\     |[]_ |
   /__________/()\    |   -|_
  /__________/    \   |    |
  | [] [] [] | [] |  _|    |
  |   ___    |    |   |–_  |
  |   |_| [] | [] |   |  –_|

         STAIRTOWER
   PERSISTENT OBJECT STORE
    a home for your data

WELCOME;

    /**
     * Version number
     */
    const VERSION = '0.0.1';

    /**
     * Key used to store meta data in JSON
     */
    const DATA_META_KEY = '__meta';

    /**
     * Key used to store the global unique identifier in JSON
     */
    const DATA_GUID_KEY = 'guid';

    /**
     * Key used to store the identifier in JSON
     */
    const DATA_ID_KEY = '_id';

    /**
     * Key used to store the database identifier in JSON
     */
    const DATA_DATABASE_KEY = 'database';

    /**
     * Request query key for expand statements
     */
    const EXPAND_KEYWORD = '$expand';

    /**
     * Request query delimiter for expand statements
     */
    const EXPAND_REQUEST_DELIMITER = '/-/';

    /**
     * Request query character to split expand statement's parts
     */
    const EXPAND_REQUEST_SPLIT_CHAR = '/';

    /**
     * Request query character to mark one-to-many relations
     */
    const EXPAND_REQUEST_TO_MANY = '*';

    /**
     * Environment variable name for the server mode
     */
    const ENVIRONMENT_KEY_SERVER_MODE = 'STAIRTOWER_SERVER_MODE';

    /**
     * Environment variable name for the server data path
     */
    const ENVIRONMENT_KEY_SERVER_DATA_PATH = 'STAIRTOWER_SERVER_DATA_PATH';
}