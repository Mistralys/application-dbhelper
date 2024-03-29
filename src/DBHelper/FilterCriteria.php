<?php
/**
 * File containing the {@link \AppDB\DBHelper_FilterCriteria} class.
 *
 * @package DBHelper
 * @see \AppDB\DBHelper_FilterCriteria
 */

namespace AppDB;

/**
 * DBHelper filter criteria class: used to specify criteria
 * for fetching data from the database. Meant to be extended
 * for a specific table, to offer table-specific selection
 * possibilities.
 *
 * @package DBHelper
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
abstract class DBHelper_FilterCriteria
{
    const ERROR_INVALID_WHERE_STATEMENT = 710001;
    
    const ERROR_EMPTY_SELECT_FIELDS_LIST = 710002;
    
    const ERROR_INVALID_SORTING_ORDER = 710003;
    
    const ERROR_MISSING_SELECT_KEYWORD = 710004;
    
    const ORDER_ASCENDING = 'ASC';
    
    const ORDER_DESCENDING = 'DESC';
    
    protected $orderField = null;
    
    protected $orderDir = 'ASC';
    
    protected $search;
    
    /**
     * The offset to start retrieving from
     * @var int
     */
    protected $offset = 0;
    
    /**
     * The limit of entries to fetch
     * @var int
     */
    protected $limit = 0;
    
    protected $joins = array();
    
   /**
    * @var string
    */
    protected $tableName;
    
   /**
    * @var string
    */
    protected $tableAlias;
    
    public function __construct()
    {
        $this->tableName = $this->getTableName();
        $this->tableAlias = $this->getTableAlias();
    }
    
    public function orderAscending() : DBHelper_FilterCriteria
    {
        return $this->setSortOrder(self::ORDER_ASCENDING);
    }
    
    public function orderDescending() : DBHelper_FilterCriteria
    {
        return $this->setSortOrder(self::ORDER_DESCENDING);
    }
    
    public function setSortOrder(string $order) : DBHelper_FilterCriteria
    {
        if(!in_array($order, array(self::ORDER_ASCENDING, self::ORDER_DESCENDING))) 
        {
            throw new DBHelper_Exception(
                'Invalid sorting order',
                null,
                self::ERROR_INVALID_SORTING_ORDER
            );
        }
        
        $this->orderDir = $order;
        
        return $this;
    }
    
    public function getSortOrder() : string
    {
        return $this->orderDir;
    }
    
    public function setSearch($search)
    {
        $search = trim($search);
        if(!empty($search)) {
            $this->search = $search;
        }
    }
    
    /**
     * Sets the limit for the list.
     * @param int $offset
     * @param int $limit
     * @return DBHelper_FilterCriteria
     */
    public function setLimit($offset = 0, $limit = 0)
    {
        $this->offset = $offset;
        $this->limit = $limit;
        return $this;
    }
    
    protected $queries = array();
    
    public function countItems()
    {
        $sql = $this->buildQuery(true);
        if (!$sql) {
            return 0;
        }
        
        $vars = $this->getQueryVariables();
        
        $items = DBHelper::fetchAll($sql, $vars);
        $this->queries[] = array(
            'sql' => $sql,
            'vars' => $vars
        );
        
        $count = 0;
        $total = count($items);
        for($i=0; $i < $total; $i++) {
            $count = $count + $items[$i]['count'];
        }
        
        return $count;
    }
    
    protected $totalUnfiltered;
    
    /**
     * Counts the total, unfiltered amount of entries.
     * @return integer
     */
    public function countUnfiltered()
    {
        if(isset($this->totalUnfiltered)) {
            return $this->totalUnfiltered;
        }
        
        $pristine = $this->createPristine();
        $this->totalUnfiltered = $pristine->countItems();
        return $this->totalUnfiltered;
    }
    
    /**
     * Creates a pristine filter instance that
     * uses the default filtering settings.
     *
     * @return DBHelper_FilterCriteria
     */
    protected function createPristine()
    {
        $class = get_class($this);
        return new $class();
    }
    
    protected function getCountSelect()
    {
        $distinct = '';
        if($this->distinct) {
            $distinct = 'DISTINCT';
        }
        
        return sprintf(
            'COUNT(%s %s) AS `count`', 
            $distinct, 
            $this->getCountColumn()
        );
    }
    
    protected function getCountColumn()
    {
        return '*';
    }
    
    protected $isCount = false;
    
    protected function buildQuery($isCount=false)
    {
        $this->isCount = $isCount;
        
        $query = $this->addDistinctKeyword($this->getQuery());
        
        if (isset($this->search)) {
            $searchTokens = $this->resolveSearchTokens();
            if(!empty($searchTokens)) {
                $this->addWhere($searchTokens);
            }
        }
        
        $replaces = array(
            '{WHAT}' => null,
            '{WHERE}' => $this->buildWhere(),
            '{GROUPBY}' => $this->buildGroupBy(),
            '{ORDERBY}' => $this->buildOrderby(),
            '{JOINS}' => $this->buildJoins(),
            '{LIMIT}' => $this->buildLimit()
        );
        
        // do this at the end, so selects can be added dynamically
        $replaces['{WHAT}'] = $this->buildSelect();
        
        return str_replace(array_keys($replaces), array_values($replaces), $query);
    }
    
   /**
    * Parses the SQL and adds the DISTINCT keyword as needed to
    * the SELECT statement if the filter has been set to distinct.
    * This is done intelligently, only adding it if it has not 
    * already been added manually.
    * 
    * @param string $query
    * @throws DBHelper_Exception
    * @return string
    */
    protected function addDistinctKeyword($query)
    {
        if($this->isCount || !$this->distinct) {
            return $query;
        }
        
        // there may be more than one select keyword in the query
        // because of subqueries, and it may already have a distinct
        // keyword. We assume that the first one we find is the
        // one we want to modify.
        $result = array();
        preg_match_all('/SELECT[ ]*DISTINCT|SELECT/sU', $query, $result, PREG_PATTERN_ORDER);
        
        if(empty($result) || !isset($result[0][0])) {
            throw new DBHelper_Exception(
                'SELECT keyword missing in the query.',
                'The query does not seem to have any SELECT keyword: '.$query,
                self::ERROR_MISSING_SELECT_KEYWORD
            );
        }
        
        // the distinct keyword has already been added
        $keyword = $result[0][0];
        if(stristr($keyword, 'distinct')) {
            return $query;
        }
         
        // add the keyword safely, by splitting the query at the position
        // of the first select instance.
        $startPos = strpos($query, $keyword);
        $endPos = $startPos + strlen($keyword);
        $start = substr($query, 0, $endPos);
        $end = substr($query, $endPos);
        $query = $start.' DISTINCT '.$end;
        
        return $query;
    }
    
    /**
     * Retrieves the select statement for the query, which
     * is used in the <code>{WHAT}</code> variable in the query.
     * When fetching the amount of records, this is automatically
     * replaced with a count statement.
     *
     * <b>Examples</b>
     *
     * An array with column names:
     * 
     * <pre>
     * array(
     *     `tablename`.`fieldname`,
     *     `tablename`.`other_fieldname`
     * )
     * </pre>
     * 
     * @return array
     */
    abstract protected function getSelect() : array;
    
    /**
     * Retrieves a list of fields in the query that gets
     * built that can be used to search in. Must return
     * an indexed array with field names.
     *
     * Example:
     *
     * array(
     *     '`field_name`',
     *     'tablename.`field_name`'
     * )
     *
     * @return string[]
     */
    abstract protected function getSearchFields() : array;
    
    abstract public function getTableName() : string;
    
    abstract public function getTableAlias() : string;
    
    /**
     * Retrieves the query to run, which is very simple
     * as it is mostly made of variables. Its purpose is
     * to set which tables the contents will be selected
     * from.
     *
     * Example:
     *
     * <pre>
     * SELECT {WHAT} FROM tablename {JOINS} {WHERE} {GROUPBY} {ORDERBY} {LIMIT}
     * </pre>
     */
    protected function getQuery()
    {
        return sprintf(
'SELECT 
    {WHAT}
FROM 
    `%s` AS %s
{JOINS} 
{WHERE} 
{GROUPBY} 
{ORDERBY} 
{LIMIT}',
            $this->getTableName(),
            $this->getTableAlias()
        );
    }
    
    protected function buildLimit() : string
    {
        if(!$this->isCount && ($this->limit > 0 || $this->offset > 0)) {
            return sprintf(" LIMIT %s OFFSET %s", $this->limit, $this->offset);
        }
        
        return '';
    }
    
    protected $where = array();
    
    protected function buildWhere()
    {
        if (empty($this->where)) {
            return '';
        }
        
        $query =
        PHP_EOL .
        'WHERE' . PHP_EOL .
        implode(PHP_EOL . 'AND' . PHP_EOL, $this->where);
        
        return $query;
    }
    
    protected function buildJoins()
    {
        return implode(PHP_EOL, $this->joins);
    }
    
    protected function buildOrderby()
    {
        // no ordering in count queries
        if ($this->isCount || !isset($this->orderField)) {
            return '';
        }

        $field = $this->orderField;
        
        // leave the name be if it has a dot (meaning it has a table or alias specified)
        if(!strstr($field, '.')) 
        {
            $field = $this->quoteColumnName($this->orderField);
            
            // add the table alias if it is present
            if(isset($this->selectAlias)) {
                $field = $this->selectAlias.'.'.$field;
            }
        }
        
        return
        PHP_EOL .
        'ORDER BY' . PHP_EOL .
        "     ".$field." ".$this->orderDir;
    }
    
    protected function quoteColumnName($name)
    {
        if(substr($name, 0, 1) == '`') {
            return $name;
        }
        
        return '`'.$name.'`';
    }
    
    protected function resolveSearchTokens()
    {
        $searchFields = $this->getSearchFields();
        if (empty($searchFields)) {
            return array();
        }
        
        $searchTerms = $this->getSearchTerms();
        if(empty($searchTerms)) {
            return array();
        }
        
        $totalTerms = count($searchTerms);
        $totalFields = count($searchFields);
        
        $parts = array();
        $connectorAdded = false;
        $like = true;
        $cnt = 0;
        for($i=0; $i<$totalTerms; $i++) 
        {
            $term = $searchTerms[$i];
            
            // escape the underscore characters, which have special
            // meaning in SQL and will not give the expected results
            $term = str_replace('_', '\_', $term);
            
            if($term=='NOT' || $term==t('NOT')) {
                $like = false;
                continue;
            }
            
            $connector = $this->getConnector($term);
            if ($connector) {
                // search terms may not start with a connector
                if($i==0) {
                    $this->addWarning('The search terms may not start with a logical operator.');
                    continue;
                }
                
                if($i==($totalTerms-1)) {
                    $this->addWarning('The search terms may not end with a logical operator.');
                    continue;
                }
                
                $parts[] = $connector;
                $connectorAdded = true;
                continue;
            } else {
                if ($i > 0 && $i < $totalTerms) {
                    if (!$connectorAdded && !empty($parts)) {
                        $parts[] = ' AND ';
                    } else {
                        $connectorAdded = false;
                    }
                }
            }
            
            $concatenator = 'OR';
            $likeToken = 'LIKE';
            if(!$like) {
                $likeToken = 'NOT LIKE';
                $concatenator = 'AND';
                $like = true;
            }
            
            $cnt++;
            $fieldTokens = array();
            for($j=0; $j<$totalFields; $j++) {
                $fieldName = $searchFields[$j];
                $placeholder = $this->generatePlaceholder('%'.$term.'%');
                $fieldTokens[] = $fieldName.' '.$likeToken.' '.$placeholder;
            }
            
            
            $parts[] = '('.implode(' '.$concatenator.' ', $fieldTokens).')';
        }
        
        return implode(' ', $parts);
    }
    
    protected static $connectors;
    
    protected function getConnector($searchTerm) : ?string
    {
        $lang = \AppLocalize\Localization::getAppLocaleName();
        
        if(!isset(self::$connectors[$lang])) 
        {
            self::$connectors[$lang] = array(
                'AND' => t('AND'),
                'OR' => t('OR')
            );
        }
        
        foreach(self::$connectors[$lang] as $connector => $translation) {
            if($searchTerm == $connector || $searchTerm == $translation) {
                return $connector;
            }
        }
        
        return null;
    }
    
    /**
     * Stores placeholders to replace with variables in the query.
     * For placeholders that should not be reset with each query,
     * set the persistent parameter to true.
     *
     * @var string $name
     * @var string $value
     */
    protected $placeholders = array();
    
    public function addPlaceholder($name, $value)
    {
        if (!substr($name, 0) == ':') {
            $name = ':' . $name;
        }
        
        $this->placeholders[$name] = $value;
        return $this;
    }
    
    /**
     * Retrieves an associative array with placeholder => value pairs of
     * variables to use in the query.
     *
     * @return array
     */
    protected function getQueryVariables()
    {
        $vars = $this->placeholders;
        
        return $vars;
    }
    
    protected function resetQueryVariables()
    {
        $this->placeholders = array();
    }
    
    /**
     * Whether to dump the final filter query. Can be used in the
     * getQuery method to show the resulting query in the UI.
     *
     * @param boolean $debug
     * @return DBHelper_FilterCriteria
     */
    public function debugQuery($debug=true)
    {
        $this->dumpQuery = $debug;
        return $this;
    }
    
    protected $dumpQuery = false;
    
    protected $columnSelects = array();
    
    /**
     * Adds a column to the select statement, to include additional
     * data in the result sets.
     *
     * @param string $columnSelect
     * @return DBHelper_FilterCriteria
     */
    public function addSelectColumn($columnSelect)
    {
        $this->columnSelects[] = $columnSelect;
        return $this;
    }
    
   /**
    * Builds the list of column names to select in 
    * the query. This automatically adds columns as
    * needed according to the settings, like whether
    * this is a distinct query.
    * 
    * @throws DBHelper_Exception
    * @return string The list of columns to select, without the SELECT keyword.
    * @see getSelect()
    * @see addSelectColumn()
    */
    protected function buildSelect()
    {
        if($this->isCount) {
            return $this->getCountSelect();
        }
        
        $selects = $this->getSelect();
        if(empty($selects)) {
            throw new DBHelper_Exception(
                'Select fields list cannot be empty',
                'The method call [getSelect] returned an empty value.',
                self::ERROR_EMPTY_SELECT_FIELDS_LIST
            );
        }
        
        if(!is_array($selects)) {
            $selects = explode(',', $selects);
        }
        
        if(!empty($this->columnSelects)) {
            $selects = array_merge($selects, $this->columnSelects);
        }
        
        // distinct queries require the ordering field to be part of the select
        if($this->distinct && !empty($this->orderField)) {
            $selects[] = $this->orderField;
        }
        
        $selects = array_unique($selects);
        
        // in distinct queries, it is safer to add all select fields to the group by
        if($this->distinct) {
            foreach($selects as $field) {
                $this->addGroupBy($field);
            }
        }
        
        return implode(',', $selects);
    }
    
    protected $distinct = false;
    
   /**
    * Sets this query as distinct: the SELECT statement will
    * automatically be changed, and other details also be 
    * adjusted, like adding the order column to the selected
    * fields for compatibility reasons for example.
    * 
    * @return DBHelper_FilterCriteria
    */
    public function makeDistinct()
    {
        $this->distinct = true;
        return $this;
    }
    
    /**
     * Retrieves all matching items as an indexed array containing
     * associative array entries with the item data.
     *
     * @return array
     * @throws DBHelper_Exception
     * @see getItem()
     */
    public function getItems() : array
    {
        $query = $this->buildQuery(false);
        if (!$query) {
            return array();
        }
        
        $vars = $this->getQueryVariables();
        $this->resetQueryVariables();
        
        $this->queries[] = array(
            'sql' => $query,
            'vars' => $vars
        );
        
        if($this->dumpQuery) {
            DBHelper::enableDebugging();
        }
        
        $result = DBHelper::fetchAll($query, $vars);
        
        if($this->dumpQuery) {
            DBHelper::disableDebugging();
        }
        
        return $result;
    }
    
    public function getQueries()
    {
        $queries = array();
        foreach ($this->queries as $def) {
            $sql = $def['sql'];
            foreach ($def['vars'] as $name => $value) {
                $sql = str_replace(':'.$name, json_encode($value), $sql);
            }
            
            $queries[] = $sql;
        }
        
        return $queries;
    }
    
    /**
     * Sets the field to order the results by.
     *
     * @param string $fieldName
     * @param string $orderDir
     * @throws DBHelper_Exception
     * @return DBHelper_FilterCriteria
     */
    public function setOrderBy(string $fieldName, string $orderDir = self::ORDER_ASCENDING)
    {
        $this->orderField = $fieldName;
        
        $this->setSortOrder($orderDir);
        
        return $this;
    }
    
    public function addWhere(string $statement)
    {
        if(empty($statement) || in_array($statement, array('()'))) {
            throw new DBHelper_Exception(
                'Invalid where statement',
                sprintf(
                    'Where statements may not be empty, and must be valid SQL conditions in statement: %s',
                    $statement
                ),
                self::ERROR_INVALID_WHERE_STATEMENT
            );
        }
        
        if(!in_array($statement, $this->where)) {
            $this->where[] = $statement;
        }
        
        return $this;
    }
    
    protected $placeholderCounter = 0;
    
    protected $placeholderHashes = array();
    
    /**
     * Generates a placeholder name unique for the specified value:
     * will return the same placeholder name for every same value
     * within the same request.
     *
     * Returns the placeholder name.
     *
     * @param string $value
     * @return mixed|string
     */
    protected function generatePlaceholder($value)
    {
        $hash = md5($value);
        if(isset($this->placeholderHashes[$hash])) {
            $pl = $this->placeholderHashes[$hash];
            
            // ensure that the placeholder value still exists
            if(!isset($this->placeholders[$pl[0]])) {
                $this->addPlaceholder($pl[0], $pl[1]);
        }
            return $pl[0];
        }
        
        $this->placeholderCounter++;
        
        // to avoid a placeholder like PH1 interfering with a
        // longer placeholder like PH11, we add zero padding.
        $name = ':PH'.sprintf('%04d', $this->placeholderCounter);
        
        $this->placeholderHashes[$hash] = array($name, $value);
        
        $this->addPlaceholder($name, $value);
        return $name;
    }
    
    public function addWhereColumnISNULL($column, $null=true)
    {
        $token = '';
        if($null===true) {
            $token = 'NOT ';
        }
        
        return $this->addWhere(sprintf(
            "%s IS %sNULL",
            $column,
            $token
        ));
    }
    
    public function addWhereColumnIN($column, $values, $exclude=false)
    {
        if(empty($values)) {
            return $this;
        }
        
        $tokens = array();
        foreach($values as $value) {
            $tokens[] = $this->generatePlaceholder($value);
        }
        
        $connector = 'IN';
        if($exclude) {
            $connector = 'NOT IN';
        }
        
        return $this->addWhere(sprintf(
            "%s %s(%s)",
            $column,
            $connector,
            implode(',', $tokens)
        ));
    }
    
    public function addWhereColumnLIKE($column, $value)
    {
        if(!strstr($column, '`') && !strstr($column, '.')) {
            $column = "`".$column."`";
        }
        
        return $this->addWhere($column." LIKE ".$this->generatePlaceholder('%'.$value.'%'));
    }
    
    public function addWhereColumnNOT_IN($column, $values)
    {
        return $this->addWhereColumnIN($column, $values, true);
    }
    
    public function addWhereColumnEquals($column, $value)
    {
        $placeholder = $this->generatePlaceholder($value);
        
        $this->addWhere(sprintf(
            "%s = %s",
            $column,
            $placeholder
        ));
    }
    
    public function addJoin($statement)
    {
        if(!in_array($statement, $this->joins)) {
            $this->joins[] = $statement;
        }
        
        return $this;
    }
    
    public function addHaving($statement)
    {
        if(!in_array($statement, $this->havings)) {
            $this->havings[] = $statement;
        }
        
        return $this;
    }
    
    public function getSearchTerms()
    {
        if (empty($this->search)) {
            return array();
        }
        
        // search for strings that are to be treated as literals:
        // all quoted string pairs. These get replaced by placeholders
        // and restored as-is after the string size check.
        $search = $this->search;
        $tokens = array();
        $literals = array();
        preg_match_all('/"([^"]+)"/i', $search, $tokens, PREG_PATTERN_ORDER);
        for ($i = 0; $i < count($tokens[0]); $i++) {
            $replace = '_LIT'.$i.'_';
            $search = str_replace($tokens[$i][0], $replace, $search);
            $literals[$replace] = $tokens[($i+1)][0];
        }
        
        $minLength = 2;
        $terms = explode(' ', $search);
        $terms = array_map('trim', $terms);

        $result = array();
        foreach ($terms as $term) 
        {
            $connector = $this->getConnector($term);
            
            if($connector !== null) {
                $result[] = $connector;
                continue;
            }
            
            if (strlen($term) < $minLength) {
                $this->addInfo(t(
                    'The term %1$s was ignored, search terms must be at least %2$s characters long.',
                    '"'.$term.'"',
                    $minLength
                ));
                continue;
            }
            
            // restore literals
            if(isset($literals[$term])) {
                $term = $literals[$term];
            }
            
            $result[] = $term;
        }

        return $result;
    }
    
    public function debug()
    {
        $queries = $this->getQueries();
        foreach($queries as $query) {
            echo '<pre>'.print_r($query ,true).'</pre>';
        }
        
        echo '<pre>Limit:'.print_r($this->limit ,true).'</pre>';
        echo '<pre>Offset:'.print_r($this->offset ,true).'</pre>';
        
        return $this;
    }
    
    protected $groupBy = array();
    
    public function addGroupBy($groupBy)
    {
        if(!in_array($groupBy, $this->groupBy)) {
            $this->groupBy[] = $groupBy;
        }
    }
    
    public function addGroupBys()
    {
        $args = func_get_args();
        foreach($args as $groupBy) {
            $this->addGroupBy($groupBy);
        }
    }
    
    protected function buildGroupBy()
    {
        if(empty($this->groupBy)) {
            return '';
        }
        
        return " GROUP BY ".implode(', ', $this->groupBy).$this->buildHavings();
    }
    
    protected $havings = array();
    
    protected function buildHavings()
    {
        if(!empty($this->havings)) {
            return " HAVING ".implode(' AND ', $this->havings);
        }
        
        return '';
    }
    
    const MESSAGE_TYPE_INFO = 'info';
    
    const MESSAGE_TYPE_WARNING = 'warning';
    
    protected function addInfo($message)
    {
        return $this->addMessage($message, self::MESSAGE_TYPE_INFO);
    }
    
    protected function addWarning($message)
    {
        return $this->addMessage($message,  self::MESSAGE_TYPE_WARNING);
    }
    
    protected $messages = array();
    
    protected function addMessage($message, $type)
    {
        $this->messages[] = array(
            'message' => $message,
            'type' => $type
        );
        
        return $this;
    }
    
    public function hasMessages()
    {
        return !empty($this->messages);
    }
    
    public function getMessages()
    {
        return $this->messages;
    }
    
    protected function resetMessages()
    {
        $this->messages = array();
    }
    
    protected function log($message)
    {
        DBHelper::log('FilterCriteria ['.get_class($this).'] | '.$message);
    }
    
    protected $criteriaItems = array();
    
    /**
     * Helper method: when selecting custom criteria to limit a
     * query, this can be used as a generic method to store values.
     *
     * Example: Selecting specific products to limit a list to.
     *
     * <pre>
     * public function selectProduct(Product $product)
     * {
     *     return $this->selectCriteriaValue('product', $product->getID());
     * }
     *
     * protected function getQuery()
     * {
     *     $this->addWhereColumnIN('`product_id`', $this->getCriteriaValues('product'));
     *
     *     [...]
     * }
     * </pre>
     *
     * @param string $type
     * @param string|NULL $value
     * @return DBHelper_FilterCriteria
     * @see selectCriteriaValues()
     */
    protected function selectCriteriaValue($type, $value)
    {
        if($value === null || $value === '') {
            return $this;
        }
        
        if(!isset($this->criteriaItems[$type])) {
            $this->criteriaItems[$type] = array();
        }
        
        if(!in_array($value, $this->criteriaItems[$type])) {
            $this->criteriaItems[$type][] = $value;
        }
        
        return $this;
    }
    
   /**
    * Selects several values at once.
    * 
    * @param string $type
    * @param array $values
    * @return DBHelper_FilterCriteria
    * @see selectCriteriaValue()
    */
    protected function selectCriteriaValues($type, $values)
    {
        if(!empty($values)) {
            foreach($values as $value) {
                $this->selectCriteriaValue($type, $value);
            }
        }
        
        return $this;
    }
    
    protected function getCriteriaValues($type)
    {
        if(isset($this->criteriaItems[$type])) {
            return $this->criteriaItems[$type];
        }
        
        return array();
    }
    
    /**
     * Selects the date ranges specified by the date string, and
     * stores the corresponding SQL statements under the given type
     * (works like the {@link selectCriteriaValue() method).
     *
     * @param string $type
     * @param string $dateSearchString
     * @return DBHelper_FilterCriteria
     */
    public function selectCriteriaDate(string $type, string $dateSearchString)
    {
        if(empty($dateSearchString)) {
            return $this;
        }
        
        $parse = new DBHelper_FilterSettings_DateParser($dateSearchString);
        if(!$parse->isValid()) {
            $this->addWarning($parse->getErrorMessage());
            return $this;
        }
        
        $result = $parse->getDates();
        foreach($result as $entry) {
            $this->selectCriteriaValue($type, $entry['sql']);
        }
        
        return $this;
    }
    
    /**
     * Adds the required WHERE statements for a date search stored
     * in the specified type, for the given column.
     *
     * @param string $type
     * @param string $column
     * @return DBHelper_FilterCriteria
     */
    public function addDateSearch($type, $column)
    {
        $values = $this->getCriteriaValues($type);
        if(!empty($values)) {
            foreach($values as $sql) {
                $this->addWhere($column.' '.$sql);
            }
        }
        
        return $this;
    }

    protected $selectAlias;
    
    protected function setSelectAlias($alias)
    {
        $this->selectAlias = $alias;
    }
}