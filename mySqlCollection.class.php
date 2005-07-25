<?php

 /**
  *  \brief Klasa zarzadzajaca kolekcjami
  *
  *  Zadaniem klasy jest ladowanie na zadanie kolekcji i przechowywanie
  *  utworzonej klasy w celu unikniecia wielokrotnego inicjalizowania tej samej
  *  kolekcji
  */

 class mySqlCollections
 {

 //! Lista kolekcji 
  private static $collections;
 
 /**
  *  \brief Pobranie kolekcji
  *  \param $name Nazwa
  */

  public function get($name)
  {
  
   if(!isset($collections[$name]))
   {

    $collection = new mySqlCollection($name);
    $collections[$name] = & $collection;
	
    return $collection;
	
   } else {
   
    return $collections[$name];
	
   };
   
  }
  
 }

 //! Element kolekcji
 
 class mySqlCollectionItem
 {

 //! Pola obiektu
  private $vars;

 //! Stan obiektu (odczyt, dodanie, zapis, usuniecie)
  public $state;

 //! Referencja do kolekcji, ktorej elementem jest obiekt
  protected $collection;
 
 /**
  *  \brief Konstruktor
  *  \param $id Id elementu
  *  \param $collection Kolekcja
  */

  function __construct($id, $collection){
  
   global $db;

   $this->collection = & $collection;

   if(!$id)
   {

    $this->state = 'add';
    $this->collection->synced = false;
    
    if($this->collection->vars)
    {

     $this->vars = $this->collection->vars;
     $this->collection->vars = false;

    };

    return;
    
   };
   
   if($this->collection->vars)
   {

    $this->vars = $this->collection->vars;
    $this->collection->vars = false;
    $this->state = 'read';
    
    return;
	
   };
   
   extract($this->collection->sQuery);
   
   $query =
   	'SELECT ' . $fields . ' ' .
   	'FROM ' . $tables . ' ' .
   	'WHERE ' .
	 '`' . $this->collection->table . '`.`id`=\'' . $id . '\'' .
     ($conditions ? (' AND ' . $conditions) : NULL);

   if(!$vars = $db->queryRow($query)){

    $this->state = "fail";
    trigger_error("Cannot load object with id ${id}");
    return;
	
   };
   
   $this->vars = $vars;
   $this->state = 'read';
   return;
   
  }

  function setState($state){ $this->state = $state; }

 /**
  *  \brief Pobranie pola obiektu
  *  \param $name Nazwa pola
  */
  
  function __get($name)
  {

   if(isset($this->vars[$name]))
   {
   
    return $this->vars[$name];
    
   } else if(isset($this->collection->config['objects'][$name])) {

    return $this->getObject($name);

   };

  }

 /**
  *  \brief Ustawienie pola obiektu
  *  \param $name Nazwa pola
  *  \param $value Nowa wartosc
  */
  
  function __set($name, $value)
  {
  
   if($this->collection->isReadOnly($name)) return;

   if($this->collection->checkData)
   {
  
    extract($this->collection->config['fields'][$name])

    if($type == 'string')
    {

     if(isset($minSize) && strlen($value) < $minSize){ $this->collection->errors[$name] = 'Wprowadzona wartosc jest za krotka'; return; };
     if(isset($maxSize) && strlen($value) > $maxSize){ $this->collection->errors[$name] = 'Wprowadzona wartosc jest za dluga'; return; };

     if(isset($empty) && 

    };

    if($type == 'int')
    {

    };

   };

   if($this->vars[$name] == $value) return;
   $this->vars[$name] = $value;
   if($this->state != 'add')
   {

    $this->state = 'update';
    $this->collection->synced = false;
     
   }

   return;

  }

 /**
  *  \brief Pobranie obiektu bedacego polem
  *  \param $name Nazwa obiektu
  */

  private function getObject($name)
  {

   global $collections;

   extract($this->collection->config['objects'][$name]);

   if(!strpos($condition, '='))
   {

    $object = $collections->get($collection)->get($this->vars[$condition]);
 
   } else {

    $arr = explode('=', $condition);
    $funcName = 'getBy' . $arr[0];
    $object = $collections->get($collection)->$funcName($this->vars[$arr[1]]);

   };

   $this->vars[$name] = & $object;
   return $object;

  }

 //! Usuniecie obiektu
  public function delete()
  {

   $this->state = 'delete';
   return true;

  }
  
 };

 /**
  *  \brief Kolekcja MySQL
  *
  *  Zadaniem kolekcji jest zapewnienie latwego dostepu do bazy MySQL bez
  *  potrzeby wprowadzania zapytan MySQL. Interfejs klasy pozwala takze na
  *  przygotowanie kolekcji dla innych systemow baz danych (PgSQL, Firebird)
  */
 
 class mySqlCollection
 {

 //! Elementy kolekcji
  private $items;

 //! Nastepne wolne ID
  private $nextId;

 //! Flaga okreslajaca czy kolekcja korzysta z wielu baz danych
  private $mltTables;
  
 //! Flaga okreslajaca czy nalezy uzywac funkcji przetwarzajaca dane po pobraniu
  protected $usePostGet;

 //! Flaga okreslajaca czy nalezy sprawdzac wartosci pol
  public $checkData;

 //! Pole wedlug ktorego ma sie odbywac sortowanie
  protected $order;

 //! Konfiguracja kolekcji
  public $config;

 //! Zapytanie MySQL dla pojedynczej bazy / rekordu  
  public $sQuery;

 //! Zapytanie MySQL dla wielu baz / rekordow
  public $mQuery;

 //! Pole przechowujace pola elementow kolekcji przy dodawaniu wielu na raz
  public $vars;

 //! Glowna tabela kolekcji
  public $table;

 //! Flaga okreslajaca czy kolekcja jest zsynchronizowana z baza MySQL
  public $synced;
  

 /**
  *  \brief Konstruktor
  *  \param $type Typ kolekcji
  */
  
  function __construct($type){
  
   global $db, $xmlConfig;
   
   $config = $xmlConfig->loadCollection($type);
   
   $this->synced = true;
   $this->vars = NULL;
   $this->usePostGet = false;
   $this->checkData = false;
   $this->nextId = -1;

   $this->config = $config;
   $this->table = $config['table'];
   $this->tables = $config['tables'];
   $this->items = Array();
   
   $this->genQueryStrings();
     
   return;
   
  }

 //! Destruktor
   
  function __destruct(){

   $this->sync();

  }

 /**
  *  \brief Metoda mapujaca wywolania metod w formacie getBy[nazwaPola]
  *
  *  Do stwierdzenia czy zazadano wywolania metody pobierajacej dane z kolekcji
  *  uzyto wyrazen regularnych.
  *
  *  \param $name Nazwa wywoˆywanej metody
  *  \param $args Tablica z parametrami wywolywanej metody
  */

  function __call($name, $args){

   if(ereg('getBy(.*)', $name, $arr)){

    $condition = '`' . strToLower($arr[1]) . '`=\'' . $args[0] . '\'';
    $limit = (isset($args[1]) ? $args[1] : NULL);

    return $this->getMultiple(NULL, $condition, $limit);

   };

  }

 /**
  *  \brief Funkcja generujaca zapytan MySQL dla podanego zakresu pol
  *
  *  Zadaniem metody jest wygenerowania zapytan MySQL na podstawie
  *  konfiguracji kolekcji oraz zakresu danych. Metoda okresla takze czy
  *  wymagane sa odwolania do wielu tabel.
  *
  *  \param $scope Zakres pol jakie nalezy pobrac z bazy
  */

  private function genQueryStrings($scope = NULL)
  {

   extract($this->config);

   if($order) $this->setOrder($order);

   // czy okreœlono jakie pola maj¹ zostaæ pobrane?
   if($scope)
   {

    $tmpTables = Array();
    $tmpFields = Array();
    $tmpFunctions = Array();
    $tmpConditions = Array();

    foreach($scope as $name)
    {

     if(isset($fields[$name]))
     {

      $tmpFields[$name] = $fields[$name];
      if(!in_array($name, $tmpTables)) $tmpTables[] = $name;

      continue;

     };

     if(isset($functions[$name]))
     {

	  $tmpFunctions[$name] = $functions[$name];
      if(!in_array($name, $tmpTables)) $tmpTables[] = $name;

     };

    };

    $tables = & $tmpTables;
    $fields = & $tmpFields;
    $functions = & $tmpFunctions;

   };

   // czy jest u¿ywana wiêcej ni¿ jedna tablica?
   if(count($tables) > 1)
   {

    $this->mltTables = true;
    $mQuery = Array();

   } else {

    $this->mltTables = false;

   };

   // inicjalizacja tablic
   $sQuery = Array();
   $sConditions = Array();

   // parsowanie pól
   foreach($fields as $field)
   {

    extract($field);

    if(!$this->mltTables)
    {

     $sQuery['fields'] .= '`' . $name . '`, ';
     if($condition) $condition = $this->fullCondition($condition, $table);
     if(!in_array($condition, $sConditions)) $sConditions[] = $condition;

    } else {

     if($name != $source)
     {

      $sQuery['fields'] .= '`' . $table . '`.`' . $source . '` AS `' . $name . '`, ';
      $mQuery[$table]['fields'] .= '`' . $source . '` AS `' . $name . '`, ';

     } else {

      $sQuery['fields'] .= '`' . $table . '`.`' . $name . '`, ';
      $mQuery[$table]['fields'] .= '`' . $name . '`, ';

     };

     $mQuery[$field['table']]['condition'] = $field['condition'];

    };

   };

   // parsowanie funkcji
   foreach($functions as $function)
   {

    if(!$this->mltTables)
    {

     $str = NULL;
     foreach($function['param'] as $param)
     {

      (($param['type'] == 'field') ? ($str .= '`' . $param['name'] . '`, ') : ($str .= '\'' .$param['content'] . '\', '));

     };

     $sQuery['fields'] .= strToUpper($function['name']) . '(' . substr($str, 0, -2) . ') AS `' . $function['result'] . '`, ';

     $condition = $this->fullCondition($function['condition'], $function['table']);
     if(!in_array($condition, $sConditions)) $sConditions[] = $condition;

    } else {

     $str = NULL;
     foreach($function['params'] as $param)
     {

      (($param['type'] == 'field') ? ($str .= '`' . $param['table'] . '`.`' . $param['name'] . '`, ') : ($str .= '\'' . $param['content'] . '\', '));

     };

     $str = strToUpper($function['name']) . '(' . substr($str, 0, -2) . ') AS `' . $function['result'] . '`, ';
     $sQuery['fields'] .= $str;

     $condition = $this->fullCondition($function['condition'], $function['table']);
     if(!in_array($condition, $sConditions)) $sConditions[] = $condition;

 	 $mQuery[$function['table']]['fields'] .= $str;
 	 $mQuery[$function['table']]['condition'] = $function['condition'];
 	 
	};
	
   };
   
   if($this->mltTables)
   {

    $this->mQuery = & $mQuery;
    foreach($mQuery as & $query) $query['fields'] = substr($query['fields'], 0, -2);
    
   };
   
   $sQuery['fields'] = substr($sQuery['fields'], 0, -2);
   $sQuery['tables'] = implode(', ', $tables);
   $sQuery['conditions'] = implode(', ', $sConditions);

   $this->sQuery = & $sQuery;

   return;

  }

 /**
  *  \brief Przeksztalcenie nazwy pola na pelny warunek potrzebny w zapytaniu
  *         MySQL
  *
  *  \param $field Nazwa pola
  *  \param $table Nazwa tabeli
  */

  private function fullCondition($field, $table)
  {

   return '`' . $table . '`.`id`=`' . $this->table . '`.`' . $field . '`';

  }

 /**
  *  \brief Sprawdzenie czy pole jest zapisywalne
  *
  *  \param $name Nazwa pola
  */
  
  public function isReadOnly($name)
  {
  
   if(isset($this->config['fields'][$name]) && ($this->config['fields'][$name]['table'] == $this->table)) return false;
   if(isset($this->config['functions'][$name])) return false;
   
   return true;
   
  }

 /**
  *  \brief Pobiranie elementu kolekcji o podanym ID
  *
  *  \param $id ID elementu
  */
  
  public function get($id)
  {

   foreach($this->items as $item) if($item->id = $id) return $item;

   $item = & new mySqlCollectionItem($id, $this);
   if($item->state == 'fail') return false;
    
   $this->items[] = & $item;
   return $item;

  }

 /**
  *  \brief Przeksztalcenie tablicy zawierajacej wiele warunkow na pojedynczy
  *         warunek MySQL
  *
  *  \param $conditions Tablica warunkow
  */

  private function parseMultiCondition($conditions)
  {

   $str = NULL;
   foreach($conditions as $condition)
   {

    $arr = explode('=', $condition);
    $str .= '`' . $this->table . '`.`' . $arr[0] . '`=\'' . $arr[1] . '\' AND ';

   };

   return substr($str, 0, -5);

  }

 /**
  *  \brief Pobranie danych z bazy
  * 
  *  Metoda pobiera dane na podstawie podanej listy pol, warunkow oraz zakresu
  *  danych
  *
  *  \param $scope Zakres pol jakie nalezy pobrac z bazy
  *  \param $conditions Warunki zapytania
  *  \param $limit Zakres danych
  */
  
  public function getMultiple($scope, $conditions, $limit)
  {

   global $db;

   if($scope) getQueryStrings($scope);

   if(is_array($conditions)) $conditions = $this->parseMultiCondition($conditions);
   
   if($this->mltTables){
   
    extract($this->mQuery[$this->table]);
   
    $query =
    'SELECT ' . $fields . ' ' .
    'FROM ' . $this->table . ' ' .
    ($conditions ? 'WHERE ' . $conditions : NULL) .
    ($this->order ? ' ORDER BY ' . $this->order : NULL) .
    ($limit ? ' LIMIT ' . $limit['offset'] . ', ' . $limit['count'] : NULL);

    $data = $db->queryRows($query);

    reset($this->mQuery);
    while($mQuery = current($this->mQuery))
    {

     $conditions = NULL;
     extract($mQuery);
     $table = key($this->mQuery);
	
     if($table != $this->table)
     {
	
      $arr = Array();
      foreach($data as & $row)
      {

       $id = $row[$condition];
       if(!in_array($id, $arr)) $arr[] = $id;

      };

      $ids = '\'' . implode('\', \'', $arr) . '\'';

      $query =
       'SELECT `id`, ' . $fields . ' ' .
       'FROM ' . $table . ' ' .
       'WHERE ' .
        ($conditions ? $conditions . ' AND ': NULL) .	   
        '`id` IN (' . $ids . ')';
	   
      $subData = $db->queryRows($query, true);

      foreach($data as & $row)
      {

       if(isset($subData[$row[$condition]]))
       {
  
        unset($subData['id']);
        $row = array_merge($subData[$row[$condition]], $row);
	   
       };
	  
      };

     };
	
     next($this->mQuery);

    };
    
   } else {

    $mainCondition = $conditions;
   
    extract($this->sQuery);

    ($conditions ? $conditions .= ' AND ' . $mainCondition : $conditions = $mainCondition);
 
    $query =
     'SELECT ' . $fields . ' ' .
     'FROM ' . $this->table . ' ' .
     ($conditions ? 'WHERE ' . $conditions : NULL) .	   	 
     ($this->order ? ' ORDER BY ' . $this->order : NULL) .
     ($limit ? ' LIMIT ' . $limit['offset'] . ', ' . $limit['count'] : NULL);

    $data = $db->queryRows($query); 

   };

   if($scope)
   {

    $this->getQueryStrings();
    
   } else {

    $items = Array();
    foreach($data as & $row)
    {

     $this->vars = $row;
     $item = new mySqlCollectionItem($row['id'], $this);

     $this->items[] = $item;
	 
     $items[] = $item;
	 
    };

    $data = & $items;
	 
   };
   
   if($this->usePostGet) return $this->postGet($data);

   return $data;

  }

 /**
  *  \brief Pobranie wszystkich rekordow z bazy
  *
  *  \param $limit Zakres danych
  */
  
  public function getAll($limit = NULL)
  {
  
   return $this->getMultiple(NULL, NULL, $limit);
   
  }

 /**
  *  \brief Dodanie rekordu do kolekcji
  *
  *  \param $data Dane rekordu
  *  \param $returnId Flaga okreslajaca czy nalezy zwrocic ID dodanego rekordu
  */
  
  public function add($data, $returnId = FALSE)
  {

   $this->vars = $data;
   $item = & new mySqlCollectionItem(0, $this);
   $this->items[] = & $item;
   $this->nextId++;
   
   if($returnId)
   {

    $this->sync();
    return $item->id;

   };
   
   return true;

  }

 //! Synchronizacja danych kolekcji z baza danych
  
  public function sync()
  {

   global $db;
   
   if($this->synced) return true;

   $query = "SELECT MAX(`id`) FROM " . $this->table;
   $this->nextId = $db->queryResult($query) + 1;
   
   $fields = Array();
   foreach($this->config['fields'] as $field) if($field['table'] == $this->table) $fields[] = $field['name'];
   $strFields = '`' . implode('`, `', $fields) . '`';

   foreach($this->items as & $item)
   {

    if(!is_object($item)) continue;

    if($item->state == 'add')
    {

     $strValues = NULL;
     foreach($fields as $field) $strValues .= '\'' . $item->$field . '\', ';
     $strValues = substr($strValues, 0, -2);

     $query =
      'INSERT ' .
      'INTO ' . $this->table . '(' . $strFields . ') ' .
      'VALUES (' . $strValues . ')';
      
     $db->queryUnbuff($query);

    };

    if($item->state == 'update')
    {
    
     $strValues = NULL;
     foreach($fields as $field) if($field != 'id') $strValues .= '`' . $field . '`=\'' . $item->$field . '\', ';
     $strValues = substr($strValues, 0, -2);

     $query =
      'UPDATE ' . $this->table . ' ' .
      'SET ' . $strValues . ' ' .
      'WHERE `id`=\'' . $item->id . '\'';
      
     $db->queryUnbuff($query);

    };

    if($item->state == 'delete')
    {

     $query =
      'DELETE FROM ' . $this->table . ' ' .
      'WHERE `id`=\'' . $item->id . '\'';
      
     $db->queryUnbuff($query);

     unset($item);

    };

    if($item) $item->setState('read');

   };
   
   return true;

  }

 /**
  *  \brief Ustawienie sortowania dla kolekcji
  *
  *  \param $fields Lista pol wg. ktorych nalezy sortowac
  *  \param $desc Flaga decydujaca o sposobie sortowania - narastajacym lub
  *         opadajacym
  */
  
  public function setOrder($fields, $desc = FALSE)
  {
  
   if(!is_array($fields))
   {
   
	if($this->config['fields'][$fields]['table'] != $this->table) return false;
    $this->order = '`' . $fields . '`' . ($desc ? ' DESC' : NULL);
	return true;
	
   } else {

	foreach($fields as $field) if($this->config['fields'][$field]['table'] != $this->table) return false;
	
	$this->order = '`' . implode('`, `', $fields) . '`' . ($desc ? ' DESC' : NULL);
	return true;

   };
   
  }

 //! Modyfikacja danych po pobraniu przez get() i getMultiple()
  
  protected function postGet($data)
  {

   return $data;

  }
 
 };
   
?>
