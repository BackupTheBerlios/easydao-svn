<?

 define('_XC_FNAME', _LPATH . 'cache/xmlConfig/index.dat');

 /**
  *  \brief Klasa zarzadzajaca plikami konfiguracyjnymi
  *
  *  Klasa odpowiedzialna za ladowanie plikow konfiguracyjnych zapisanych w
  *  formacie XML, parser dla odpowiedniego rodzaju pliku konfiguracyjnego jest
  *  ladowany z katalogu include/xmlConfigParsers. Aby nie parsowac wiele razy
  *  tego samego pliku przetworzone dane zostaja zserializowane i zapisane w
  *  katalogu cache/xmlConfig
  */

 class xmlConfig
 {

 /**
  *  \brief Index sparsowanych plikow XML
  *
  *  Tablica przechowujaca informacje o dacie ostatniej zmiany plikow
  *  konfiguracyjnych. Mozna dzieki niej okreslic czy konkretny plik wymaga
  *  sparsowania.
  */

  private $index;

 //! Flaga zmiany indexu.

  private $indexChanged;

 //! Zmienna przechowujaca zaladowane pliki konfiguracyjne

  private $data;

 /**
  *  \brief Konstruktor
  *
  *  Zadaniem konstruktora jest zaladowanie index'u sparsowanych plikow.
  */

  function __construct()
  {

   $this->indexChanged = FALSE;

   if(file_exists(_XC_FNAME))
   {

    if(!$this->index = unserialize(file_get_contents(_XC_FNAME))) trigger_error('Unserializing cache index failed!', E_USER_ERROR);

   };

   return;

  }

 /**
  *  \brief Destruktor
  *
  *  Zadaniem destruktora jest w razie stwierdzenia zmian w index'ie
  *  sparsowanych plikow zmiany zapisanie index'u
  */

  public function __destruct()
  {

   if($this->indexChanged)
   {

    $f = fopen(_XC_FNAME, "w");
    fwrite($f, serialize($this->index));
    fclose($f);

   };

   return;

  }

 /**
  *  \brief Metoda mapujaca wywolania metod w formacie load[typKonfiguracji]
  *
  *  Do stwierdzenia czy zazadano wywolania metody ladujacej konfiguracje o
  *  podanym formacie uzyto wyrazen regularnych. 
  *
  *  \param $name Nazwa wywoˆywanej metody
  *  \param $args Tablica z parametrami wywolywanej metody
  */

  public function __call($name, $args)
  {

   if(ereg('load(.*)', $name, $arr)) return $this->load($args[0], strToLower($arr[1]));
   return false;

  }

 /**
  *  \brief Ladujowanie pliku konfiguracyjnego
  *
  *  Zadaniem metody jest okreslenie czy podany plik konfiguracyjny zostal juz
  *  sparsowany oraz czy jego rozmiar i data nie ulegly zmianie. Jesli tak sie
  *  nie stalo plik zostaje zaladowany z cache'u, w innym wypadku nastepuje
  *  zaladowanie parsera dla podanego typu konfiguracji i sparsowanie danego
  *  pliku oraz zapisanie wyniku do cache'u
  *
  *  \param $name Nazwa pliku do zaladowania
  *  \param $type Rodzaj konfiguracji
  *  \return Dane z pliku konfiguracyjnego
  */

  public function load($name, $type)
  {

   if(strpos($name, '.') === false)
   {
   
    $fname = _LPATH . 'config/' . $name . '.' . $type . '.xml';
	
   } else {
   	
    $path = substr($name, 0, strrpos($name, '.') + 1);
    $file = substr($name, strrpos($name, '.') + 1);
   
    $fname = _LPATH . 'packages/' . str_replace('.', '/', $path) . 'config/' . $file . '.' . $type . '.xml';
	
   };
   
   $cname = _LPATH . 'cache/xmlConfig/' . $name . '.' . $type . '.dat';

   if(!file_exists($fname)) trigger_error('Config file <i>' . $fname . '</i> not found', E_USER_ERROR);

   $fdate = filemtime($fname);
   $fsize = filesize($fname);

   if(isset($this->index[$name]))
   {

    extract($this->index[$name]);
    if(($date == $fdate) && ($size == $fsize)){

     if(!$data = unserialize(file_get_contents($cname))) trigger_error('Unserializing config file <i>' . $name . '</i> for module <i>' . $module . '</i> failed', E_USER_ERROR);
     return $data;

    };
    
   };

   $className = 'xml' . ucfirst($type) . 'ConfigParser';

   if(!class_exists($className)) include('include/xmlConfigParsers/xml' . $type . 'ConfigParser.class.php');
   $parser = new $className($fname);

   if(!file_put_contents($cname, serialize($parser->result))) trigger_error('Creating cache of config file <i>' . $name . '</i> for module <i>' . $module . '</i> failed!', E_USER_ERROR);

   $this->index[$name] = Array("date" => $fdate, "size" => $fsize);
   $this->indexChanged = TRUE;
   
   return $parser->result;

  }

 };

?>
