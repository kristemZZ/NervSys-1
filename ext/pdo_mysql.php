<?php

/**
 * SQL Execution for PDO Extension
 *
 * Author 空城 <694623056@qq.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 空城
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

class pdo_mysql extends pdo
{
    /**
     * Extension config
     * Config is an array composed of the following elements:
     *
     * 'init'    => false       //bool: PDO re-connect option
     * 'type'    => 'mysql'     //string: PDO DSN prefix (database type)
     * 'host'    => '127.0.0.1' //string: Database host address
     * 'port'    => 3306        //int: Database host port
     * 'user'    => 'root'      //string: Database username
     * 'pwd'     => ''          //string: Database password
     * 'db_name' => ''          //string: Database name
     * 'charset' => 'utf8mb4'   //string: Database charset
     * 'persist' => true        //string: Persistent connection option
     *
     * Config will be removed once used
     * Do add 'init' => true to re-connect
     *
     * @var array
     */
    public static $config = [
        'type'    => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'root',
        'pwd'     => '',
        'db_name' => '',
        'charset' => 'utf8mb4',
        'persist' => true
    ];

    //MySQL instance resource
    private static $db_mysql = null;

    /**
     * Extension Initialization
     */
    private static function init(): void
    {
        //No reconnection
        if ((!isset(self::$config['init']) || false === (bool)self::$config['init']) && is_object(self::$db_mysql)) return;

        //Read new config
        $cfg = ['type', 'host', 'port', 'user', 'pwd', 'db_name', 'charset', 'persist'];

        if (!empty(self::$config)) {
            //Set config for PDO
            foreach ($cfg as $key) if (isset(self::$config[$key])) self::$$key = self::$config[$key];
            //Remove config
            self::$config = [];
        }

        //Connect MySQL
        self::$db_mysql = self::connect();

        //Free memory
        unset($cfg, $key);
    }

    /**
     * Insert data
     *
     * @param string $table
     * @param array  $data
     * @param string $last
     *
     * @return bool
     */
    public static function insert(string $table, array $data = [], string &$last = 'id'): bool
    {
        //No data to insert
        if (empty($data)) {
            debug('No data to insert!');
            return false;
        }

        //Build "data"
        $column = self::build_data($data);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'INSERT INTO ' . self::escape($table) . ' (' . implode(', ', $column) . ') VALUES(' . implode(', ', array_keys($column)) . ')';
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        $last = '' === $last ? (string)self::$db_mysql->lastInsertId() : (string)self::$db_mysql->lastInsertId($last);

        unset($table, $data, $column, $sql, $stmt);
        return $result;
    }

    /**
     * Update data
     *
     * @param string $table
     * @param array  $data
     * @param array  $where
     *
     * @return bool
     */
    public static function update(string $table, array $data, array $where): bool
    {
        //No data to update
        if (empty($data)) {
            debug('No data to update!');
            return false;
        }

        //Build "data"
        $data_column = self::build_data($data);

        //Get "SET"
        $set_opt = [];
        foreach ($data_column as $key => $item) $set_opt[] = $item . ' = ' . $key;
        unset($data_column, $key, $item);

        //Build "where"
        $where_opt = self::build_where($where);

        //Merge data
        $data = array_merge($data, $where);
        unset($where);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'UPDATE ' . self::escape($table) . ' SET ' . implode(', ', $set_opt) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($table, $data, $set_opt, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Select data
     *
     * @param string $table
     * @param array  $option
     * @param bool   $column
     *
     * @return array
     */
    public static function select(string $table, array $option = [], bool $column = false): array
    {
        //Build options
        $opt = self::build_opt($option);
        $data = &$opt['data'];
        $field = &$opt['field'];
        unset($opt['data'], $opt['field']);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'SELECT ' . $field . ' FROM ' . self::escape($table);

        foreach ($opt as $item) $sql .= $item;

        $stmt = self::$db_mysql->prepare($sql);
        !empty($data) ? $stmt->execute($data) : $stmt->execute();

        $result = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($table, $option, $column, $opt, $data, $field, $sql, $stmt);
        return $result;
    }

    /**
     * Delete data
     *
     * @param string $table
     * @param array  $where
     *
     * @return bool
     */
    public static function delete(string $table, array $where): bool
    {
        //Delete not allowed
        if (empty($where)) {
            debug('Delete is not allowed!');
            return false;
        }

        //Build "where"
        $where_opt = self::build_where($where);

        //Prepare & execute SQL
        self::init();

        $sql = 'DELETE ' . self::escape($table) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($where);

        unset($table, $where, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Build "data"
     *
     * @param array $data
     *
     * @return array
     */
    private static function build_data(array &$data): array
    {
        //Columns
        $column = [];

        //Process data
        foreach ($data as $key => $value) {
            //Generate bind value
            $bind = ':d_' . $key;

            //Add to columns
            $column[$bind] = self::escape($key);

            //Renew data
            unset($data[$key]);
            $data[$bind] = $value;
        }

        unset($key, $value, $bind);
        return $column;
    }

    /**
     * Build "where"
     *
     * @param array $where
     *
     * @return array
     */
    private static function build_where(array &$where): array
    {
        //Options
        $option = ['WHERE'];

        foreach ($where as $key => $item) {
            unset($where[$key]);

            //Add operator
            if (2 === count($item)) {
                $item[2] = $item[1];
                $item[1] = '=';
            }

            //Generate bind value
            $bind = ':w_' . $item[0] . '_' . mt_rand();

            //Add to "where"
            $where[$bind] = $item[2];

            //Process data
            if (isset($item[3])) {
                $item[3] = strtoupper($item[3]);
                if (in_array($item[3], ['AND', 'OR', 'NOT'], true)) $option[] = $item[3];
            }

            $option[] = self::escape($item[0]);
            $option[] = $item[1];
            $option[] = $bind;
        }

        unset($key, $item, $bind);
        return $option;
    }

    /**
     * Build options
     *
     * @param array $opt
     *
     * @return array
     */
    private static function build_opt(array $opt = []): array
    {
        $option = [];
        $option['data'] = [];

        //Process "field"
        if (isset($opt['field'])) {
            if (is_array($opt['field']) && !empty($opt['field'])) {
                $column = [];
                foreach ($opt['field'] as $value) $column[] = self::escape($value);
                if (!empty($column)) $option['field'] = implode(', ', $column);
                unset($column, $value);
            } elseif (is_string($opt['field']) && '' !== $opt['field']) $option['field'] = &$opt['field'];
        } else $option['field'] = '*';

        //Process "join"
        if (isset($opt['join'])) {
            if (is_array($opt['join']) && !empty($opt['join'])) {
                $join_data = [];

                foreach ($opt['join'] as $table => $value) {
                    $value[3] = !isset($value[3]) ? 'INNER' : strtoupper($value[3]);
                    if (!in_array($value[3], ['INNER', 'LEFT', 'RIGHT'], true)) $value[3] = 'INNER';

                    if (2 === count($value)) {
                        $value[2] = $value[1];
                        $value[1] = '=';
                    }

                    $join_data[] = $value[3] . ' JOIN ' . self::escape($table) . ' ON ' . $value[0] . ' ' . $value[1] . ' ' . $value[2];
                }

                if (!empty($join_data)) $option['join'] = implode(' ', $join_data);
                unset($join_data, $table, $value);
            } elseif (is_string($opt['join']) && '' !== $opt['join']) $option['join'] = false !== stripos($opt['join'], 'JOIN') ? $opt['join'] : 'INNER JOIN ' . $opt['join'];
        }

        //Process "where"
        if (isset($opt['where'])) {
            if (is_array($opt['where']) && !empty($opt['where'])) {
                $option['where'] = implode(' ', self::build_where($opt['where']));
                $option['data'] = array_merge($option['data'], $opt['where']);
            } elseif (is_string($opt['where']) && '' !== $opt['where']) $option['where'] = 'WHERE ' . $opt['where'];
        }

        //Process "order"
        if (isset($opt['order'])) {
            if (is_array($opt['order']) && !empty($opt['order'])) {
                $column = [];

                foreach ($opt['order'] as $key => $value) {
                    $value = strtoupper($value);
                    if (!in_array($value, ['DESC', 'ASC'], true)) $value = 'DESC';

                    $column[] = self::escape($key) . ' ' . $value;
                }

                if (!empty($column)) $option['order'] = 'ORDER BY ' . implode(', ', $column);
                unset($column, $key, $value);
            } elseif (is_string($opt['order']) && '' !== $opt['order']) $option['order'] = 'ORDER BY ' . $opt['order'];
        }

        //Process "group"
        if (isset($opt['group'])) {
            if (is_array($opt['group']) && !empty($opt['group'])) {
                $column = [];
                foreach ($opt['group'] as $key) $column[] = self::escape($key);

                if (!empty($column)) $option['group'] = 'GROUP BY ' . implode(', ', $column);
                unset($column, $key);
            } elseif (is_string($opt['group']) && '' !== $opt['group']) $option['group'] = 'GROUP BY ' . $opt['group'];
        }

        //Process "limit"
        if (isset($opt['limit'])) {
            if (is_array($opt['limit']) && !empty($opt['limit'])) {
                if (1 === count($opt['limit'])) {
                    $opt['limit'][1] = $opt['limit'][0];
                    $opt['limit'][0] = 0;
                }

                $option['limit'] = 'LIMIT :l_start, :l_offset';
                $option['data'] = array_merge($option['data'], [':l_start' => (int)$opt['limit'][0], ':l_offset' => (int)$opt['limit'][1]]);
            } elseif (is_numeric($opt['limit'])) {
                $option['limit'] = 'LIMIT :l_start, :l_offset';
                $option['data'] = array_merge($option['data'], [':l_start' => 0, ':l_offset' => (int)$opt['limit']]);
            } elseif (is_string($opt['limit']) && '' !== $opt['limit']) $option['limit'] = 'LIMIT ' . $opt['limit'];
        }

        unset($opt);
        return $option;
    }

    /**
     * Escape column key
     *
     * @param string $value
     *
     * @return string
     */
    private static function escape(string $value): string
    {
        return false !== strpos($value, '.') ? '`' . trim($value, " `\t\n\r\0\x0B") . '`' : trim($value);
    }
}